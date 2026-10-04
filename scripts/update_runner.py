#!/usr/bin/env python3
"""Single-pass, non-root updater. Fixed public repository; no user-supplied commands.

Started ONLY by `php artisan lagos:updates-work` under a deployment account.
SQLite + database queue + file maintenance mode. Never automatically restores a
live database or overwrites local changes. See docs/ATUALIZACOES-PELO-PAINEL.md.
"""
import argparse, datetime, fcntl, hashlib, json, os, re, shutil, signal, sqlite3
import stat, subprocess, tarfile, time, urllib.request, uuid
from contextlib import closing
from pathlib import Path

REPOSITORY='https://github.com/eduhdev021/lagospanel.git'
API='https://api.github.com/repos/eduhdev021/lagospanel'
SHA=re.compile(r'^[a-f0-9]{40}$')
class Blocked(Exception): pass

def now(): return datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
def digest(path):
 h=hashlib.sha256()
 with open(path,'rb')as f:
  for block in iter(lambda:f.read(1048576),b''):h.update(block)
 return h.hexdigest()
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args,**kwargs):return None

def approved_head():
 opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect())
 def get(path):
  req=urllib.request.Request(API+path,headers={'Accept':'application/vnd.github+json','User-Agent':'LagosPanel-Updater/1'})
  with opener.open(req,timeout=12)as response:
   raw=response.read(2097153)
   if len(raw)>2097152:raise Blocked('github')
   return json.loads(raw)
 repo=get('')
 if repo.get('full_name')!='eduhdev021/lagospanel' or repo.get('private') is not False or repo.get('archived') is not False:raise Blocked('github')
 sha=get('/commits/main').get('sha','')
 if not isinstance(sha,str)or not SHA.fullmatch(sha):raise Blocked('github')
 runs=[r for r in get('/actions/runs?head_sha='+sha+'&per_page=100').get('workflow_runs',[]) if r.get('head_sha')==sha and r.get('head_branch')=='main' and r.get('event')=='push' and r.get('path')=='.github/workflows/tests.yml' and r.get('head_repository',{}).get('full_name')=='eduhdev021/lagospanel']
 runs.sort(key=lambda r:r.get('id',0),reverse=True)
 if not runs or runs[0].get('status')!='completed' or runs[0].get('conclusion')!='success':raise Blocked('ci')
 return sha

class Runner:
 def __init__(self,root,database,php,composer):
  self.root=Path(root).resolve();self.database=Path(database).resolve();self.php=str(Path(php).resolve());self.composer=str(Path(composer).resolve())
  self.private=self.root/'.cache/panel-updater';self.pause=self.root/'storage/framework/panel-update-pause';self.down=self.root/'storage/framework/down'
  self.db=None;self.job=None;self.phase='preflight';self.maintenance=False;self.pause_owned=False;self.mutated=False;self.stage=None;self.backup=None;self.log=None
  self.env=os.environ.copy()
  for key in list(self.env):
   if key.startswith('GIT_')or key in ('COMPOSER_AUTH','COMPOSER','COMPOSER_HOME','COMPOSER_VENDOR_DIR','COMPOSER_ALLOW_SUPERUSER'):self.env.pop(key,None)
  self.env.update({'GIT_TERMINAL_PROMPT':'0','GIT_CONFIG_NOSYSTEM':'1','GIT_CONFIG_GLOBAL':'/dev/null'})
 def run(self,args,cwd=None,timeout=120,capture=False):
  with open(self.log,'ab')as log:
   process=subprocess.Popen(list(map(str,args)),cwd=cwd or self.root,env=self.env,stdin=subprocess.DEVNULL,stdout=subprocess.PIPE if capture else log,stderr=log,start_new_session=True)
   try:out,_=process.communicate(timeout=timeout)
   except subprocess.TimeoutExpired:
    os.killpg(process.pid,signal.SIGKILL);process.communicate();raise Blocked(self.phase)
   if process.returncode!=0:raise Blocked(self.phase)
   if capture:
    if len(out)>4194304:raise Blocked(self.phase)
    return out.decode('utf-8').strip()
   return ''
 def git(self,*args,**kwargs):
  return self.run(['git','-c','safe.directory='+str(self.root),'-c','core.hooksPath=/dev/null','-c','core.fsmonitor=false','-c','credential.helper=','-c','protocol.file.allow=never','-c','protocol.ext.allow=never','-c','http.sslVerify=true',*args],**kwargs)
 def basic(self):
  if os.geteuid()==0:raise Blocked('root_forbidden')
  for path in [self.root,self.root/'.git',self.root/'.cache',self.root/'bootstrap/cache']:
   if not path.is_dir()or path.is_symlink()or path.stat().st_uid!=os.geteuid()or path.stat().st_mode & 0o022:raise Blocked('ownership')
  if not self.database.is_file()or not self.database.is_relative_to(self.root):raise Blocked('database')
  if not Path(self.php).is_file()or not Path(self.composer).is_file():raise Blocked('tools')
  if (self.root/'.env').is_symlink()or not (self.root/'.env').is_file():raise Blocked('environment')
  if (self.root/'.cache/vendor').is_symlink():raise Blocked('ownership')
  if self.private.exists() and (self.private.is_symlink()or self.private.stat().st_uid!=os.geteuid()):raise Blocked('ownership')
  self.private.mkdir(mode=0o700,exist_ok=True);os.chmod(self.private,0o700)
 def connect(self):
  self.db=sqlite3.connect(str(self.database),timeout=15,isolation_level=None);self.db.row_factory=sqlite3.Row;self.db.execute('PRAGMA foreign_keys=ON')
 def heartbeat(self):
  self.db.execute('INSERT OR IGNORE INTO admin_preferences(id,version,created_at,updated_at) VALUES(1,0,?,?)',(now(),now()))
  self.db.execute('UPDATE admin_preferences SET updater_heartbeat_at=?,updated_at=? WHERE id=1',(now(),now()))
 def claim(self):
  self.db.execute('BEGIN IMMEDIATE')
  try:
   if self.db.execute("SELECT 1 FROM panel_updates WHERE status='running' LIMIT 1").fetchone():self.db.commit();return False
   row=self.db.execute("SELECT * FROM panel_updates WHERE status='pending' ORDER BY id LIMIT 1").fetchone()
   if row is None:self.db.commit();return False
   self.job=dict(row);self.db.execute("UPDATE panel_updates SET status='running',started_at=?,updated_at=? WHERE id=? AND status='pending'",(now(),now(),row['id']));self.db.commit();return True
  except Exception:self.db.rollback();raise
 def note(self,phase):
  self.phase=phase;row=self.db.execute('SELECT events FROM panel_updates WHERE id=?',(self.job['id'],)).fetchone();events=json.loads(row['events']or'[]');events.append({'at':now(),'phase':phase})
  self.db.execute('UPDATE panel_updates SET phase=?,events=?,updated_at=? WHERE id=?',(phase,json.dumps(events),now(),self.job['id']))
  (self.backup/'journal.json').write_text(json.dumps({'update_id':self.job['id'],'phase':phase,'events':events}))
 def finish(self,status,phase):
  self.note(phase);self.db.execute('UPDATE panel_updates SET status=?,finished_at=?,updated_at=? WHERE id=?',(status,now(),now(),self.job['id']))
  self.db.execute('INSERT INTO audit_events(user_id,event,subject,context,created_at) VALUES(?,?,?,?,?)',(self.job['user_id'],'panel.update_'+status,'panel_update:'+str(self.job['id']),json.dumps({'phase':phase,'target':self.job['target_sha']}),now()))
 def preflight(self):
  job=self.job
  if not SHA.fullmatch(job['source_sha'])or not SHA.fullmatch(job['target_sha'])or job['source_sha']==job['target_sha']:raise Blocked('invalid_request')
  if not job['approved_at']or datetime.datetime.strptime(job['approved_at'],'%Y-%m-%d %H:%M:%S')<datetime.datetime.now(datetime.timezone.utc).replace(tzinfo=None)-datetime.timedelta(minutes=15):raise Blocked('expired')
  actor=self.db.execute('SELECT is_admin,email_verified_at FROM users WHERE id=?',(job['user_id'],)).fetchone()
  if not actor or not actor['is_admin']or not actor['email_verified_at']:raise Blocked('authorization')
  if self.down.exists()or self.pause.exists():raise Blocked('existing_maintenance')
  if self.git('rev-parse','HEAD',capture=True)!=job['source_sha']:raise Blocked('source_changed')
  if self.git('status','--porcelain','--untracked-files=all',capture=True):raise Blocked('local_changes')
  if shutil.disk_usage(self.root).free<536870912:raise Blocked('disk_space')
  # Hooks and URL rewrite/proxy rules can turn a fixed public fetch into another destination.
  config=self.git('config','--local','--list',capture=True)
  if re.search(r'(?im)^(url\.|remote\..*\.proxy=|http\..*proxy=|http\.proxy=|core\.gitproxy=|filter\.)',config):raise Blocked('git_config')
 def fetch(self):
  self.note('github')
  if approved_head()!=self.job['target_sha']:raise Blocked('target_changed')
  self.git('fetch','--no-tags','--no-recurse-submodules',REPOSITORY,'refs/heads/main',timeout=180)
  if self.git('rev-parse','FETCH_HEAD',capture=True)!=self.job['target_sha']:raise Blocked('target_changed')
  self.git('merge-base','--is-ancestor',self.job['source_sha'],self.job['target_sha'])
  listing=self.git('ls-tree','-r',self.job['target_sha'],capture=True)
  for line in listing.splitlines():
   metadata,path=line.split('\t',1);mode=metadata.split()[0]
   if mode not in ('100644','100755')or (path.startswith('.env')and path!='.env.example')or path.startswith(('.cache/','.git/'))or (path.startswith(('storage/','bootstrap/cache/'))and not path.endswith('/.gitignore'))or re.search(r'\.(sqlite|sqlite-wal|sqlite-shm|db|log)$',path):raise Blocked('protected_paths')
  if not any(line.endswith('\tartisan')for line in listing.splitlines()):raise Blocked('invalid_target')
  self.stage=self.root/'.cache'/('panel-stage-'+str(self.job['id'])+'-'+uuid.uuid4().hex)
  self.git('worktree','add','--detach',str(self.stage),self.job['target_sha'])
  self.note('dependencies')
  self.env['COMPOSER_HOME']=str(self.private/'composer-home');self.env['COMPOSER_VENDOR_DIR']=str(self.stage/'.cache/vendor')
  self.run([self.php,self.composer,'install','--no-dev','--no-interaction','--prefer-dist','--no-scripts','--no-plugins','--optimize-autoloader'],cwd=self.stage,timeout=900)
  self.env.pop('COMPOSER_VENDOR_DIR',None)
  if not (self.stage/'.cache/vendor/autoload.php').is_file():raise Blocked('dependencies')
  self.run([self.php,self.composer,'check-platform-reqs','--no-dev'],cwd=self.stage,timeout=60)
 def drain(self):
  self.note('maintenance')
  # Recheck immediately before taking the application down.
  if self.git('rev-parse','HEAD',capture=True)!=self.job['source_sha']or self.git('status','--porcelain','--untracked-files=all',capture=True):raise Blocked('local_changes')
  if self.down.exists()or self.down.is_symlink()or self.pause.exists()or self.pause.is_symlink():raise Blocked('existing_maintenance')
  with self.pause.open('x')as f:f.write(str(self.job['id']))
  self.pause_owned=True;os.chmod(self.pause,0o640)
  with self.down.open('x')as f:json.dump({'time':int(time.time()),'status':503,'retry':15,'refresh':15,'panel_update':self.job['id']},f)
  self.maintenance=True;os.chmod(self.down,0o640)
  time.sleep(30)  # Allow ordinary in-flight HTTP requests to finish.
  deadline=time.monotonic()+95
  while self.db.execute('SELECT count(*) FROM jobs WHERE reserved_at IS NOT NULL').fetchone()[0]:
   if time.monotonic()>=deadline:raise Blocked('busy_workers')
   time.sleep(2)
 def backup_data(self):
  self.note('backup')
  self.env_hash=digest(self.root/'.env')
  shutil.copy2(self.root/'.env',self.backup/'.env');os.chmod(self.backup/'.env',0o600)
  with closing(sqlite3.connect(self.backup/'database.sqlite'))as destination:
   self.db.backup(destination)
   if destination.execute('PRAGMA integrity_check').fetchone()[0]!='ok':raise Blocked('backup')
  os.chmod(self.backup/'database.sqlite',0o600)
  self.git('archive','--format=tar','--output='+str(self.backup/'source.tar'),self.job['source_sha'])
  with tarfile.open(self.backup/'storage-app.tar.gz','w:gz')as archive:
   archive.add(self.root/'storage/app',arcname='storage/app',recursive=True)
  manifest={name:digest(self.backup/name)for name in ['.env','database.sqlite','source.tar','storage-app.tar.gz']}
  (self.backup/'manifest.json').write_text(json.dumps({'source':self.job['source_sha'],'target':self.job['target_sha'],'sha256':manifest},indent=2))
 def apply(self):
  self.note('applying');self.mutated=True
  self.git('merge','--ff-only','--no-edit',self.job['target_sha'])
  vendor=self.root/'.cache/vendor'
  if vendor.exists():vendor.rename(self.backup/'vendor.previous')
  (self.stage/'.cache/vendor').rename(vendor)
  for name in ['config.php','packages.php','services.php','events.php','routes-v7.php','routes.php']:
   p=self.root/'bootstrap/cache'/name
   if p.is_symlink():raise Blocked('cache')
   if p.is_file():shutil.copy2(p,self.backup/('cache-'+name));p.unlink()
  self.note('migrations')
  self.run([self.php,'artisan','package:discover','--no-interaction'],timeout=120)
  self.run([self.php,'artisan','migrate','--force','--no-interaction'],timeout=300)
  self.note('validation')
  for command in ['config:cache','route:cache','view:cache','queue:restart']:
   self.run([self.php,'artisan',command,'--no-interaction'],timeout=120)
  if digest(self.root/'.env')!=self.env_hash or self.git('rev-parse','HEAD',capture=True)!=self.job['target_sha']:raise Blocked('environment_changed')
  self.resume()
  self.finish('succeeded','completed')
 def resume(self):
  if self.down.is_symlink()or self.pause.is_symlink():raise Blocked('maintenance')
  if json.loads(self.down.read_text()).get('panel_update')!=self.job['id']or self.pause.read_text()!=str(self.job['id']):raise Blocked('maintenance')
  self.down.unlink();self.maintenance=False;self.pause.unlink();self.pause_owned=False
 def process(self):
  self.backup=self.private/('update-'+str(self.job['id'])+'-'+uuid.uuid4().hex);self.backup.mkdir(mode=0o700);self.log=self.backup/'process.log';self.log.touch(mode=0o600)
  self.db.execute('UPDATE panel_updates SET backup_path=? WHERE id=?',(str(self.backup),self.job['id']))
  try:
   self.note('preflight');self.preflight();self.fetch();self.drain();self.backup_data();self.apply()
  except Exception as error:
   reason=str(error)if isinstance(error,Blocked)else self.phase
   allowed={'preflight','root_forbidden','ownership','database','tools','environment','invalid_request','expired','authorization','existing_maintenance','source_changed','local_changes','disk_space','git_config','github','ci','target_changed','protected_paths','invalid_target','dependencies','maintenance','maintenance_driver','busy_workers','backup','applying','cache','migrations','validation','environment_changed'}
   if reason not in allowed:reason='preflight'
   # Never roll back a live database. Before source changes, safely end our own maintenance.
   if not self.mutated and self.maintenance:
    try:self.resume()
    except Exception:reason='maintenance'
   elif not self.mutated and self.pause_owned and not self.pause.is_symlink()and self.pause.read_text()==str(self.job['id']):self.pause.unlink(missing_ok=True)
   try:self.finish('failed'if self.mutated or self.maintenance else'blocked',reason)
   except Exception:(self.backup/'recovery-required.json').write_text(json.dumps({'id':self.job['id'],'phase':reason,'source_changed':self.mutated}))
   print('Atualização não concluída. Consulte o histórico; fase: '+reason,flush=True)
  finally:
   if self.stage and self.stage.exists():
    try:self.git('worktree','remove','--force',str(self.stage),timeout=60)
    except Exception:pass
 def once(self):
  self.basic()
  with open(self.private/'worker.lock','a')as lock:
   try:fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
   except BlockingIOError:return
   self.connect()
   try:
    self.heartbeat()
    if self.claim():self.process()
   finally:self.db.close()

def main():
 parser=argparse.ArgumentParser();parser.add_argument('--root',required=True);parser.add_argument('--database',required=True);parser.add_argument('--php',required=True);parser.add_argument('--composer',required=True);args=parser.parse_args()
 os.umask(0o027)
 try:Runner(args.root,args.database,args.php,args.composer).once()
 except Exception as error:
  reason=str(error)if isinstance(error,Blocked)else'worker_configuration'
  print('Worker não iniciou: '+reason);return 1
 return 0
if __name__=='__main__':raise SystemExit(main())
