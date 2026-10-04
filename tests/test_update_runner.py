"""Local Git/SQLite fixtures. No remote fetch, Composer network or real deployment."""
import importlib.util,json,os,sqlite3,subprocess,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('updater',Path(__file__).resolve().parents[1]/'scripts/update_runner.py')
u=importlib.util.module_from_spec(spec);spec.loader.exec_module(u)
class UpdaterTest(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory();self.root=Path(self.tmp.name)
  for p in ['.cache','bootstrap/cache','storage/framework','storage/app','database']:(self.root/p).mkdir(parents=True,exist_ok=True)
  (self.root/'.gitignore').write_text('.env\n.cache/\nbootstrap/cache/\nstorage/\ndatabase/\n')
  (self.root/'.env').write_text('APP_KEY=fixture-preserved\n')
  (self.root/'artisan').write_text('old source')
  self.git('init','-q');self.git('config','user.email','fixture@example.invalid');self.git('config','user.name','Fixture');self.git('add','.');self.git('commit','-qm','source');self.source=self.git('rev-parse','HEAD')
  (self.root/'artisan').write_text('new source');self.git('commit','-qam','target');self.target=self.git('rev-parse','HEAD');self.git('checkout','-q',self.source)
  db=self.root/'database/database.sqlite';c=sqlite3.connect(db)
  c.executescript('CREATE TABLE users(id INTEGER PRIMARY KEY,is_admin INTEGER,email_verified_at TEXT); INSERT INTO users VALUES(1,1,"verified"); CREATE TABLE jobs(id INTEGER,reserved_at INTEGER); CREATE TABLE admin_preferences(id INTEGER PRIMARY KEY,version INTEGER,created_at TEXT,updated_at TEXT,updater_heartbeat_at TEXT); CREATE TABLE panel_updates(id INTEGER PRIMARY KEY,user_id INTEGER,source_sha TEXT,target_sha TEXT,status TEXT,phase TEXT,events TEXT,backup_path TEXT,approved_at TEXT,started_at TEXT,finished_at TEXT,created_at TEXT,updated_at TEXT); CREATE TABLE audit_events(user_id INTEGER,event TEXT,subject TEXT,context TEXT,created_at TEXT);')
  c.execute('INSERT INTO panel_updates(id,user_id,source_sha,target_sha,status,approved_at) VALUES(1,1,?,?,?,?)',(self.source,self.target,'pending',u.now()));c.commit();c.close()
  self.r=u.Runner(self.root,db,'/bin/true','/bin/true');self.r.basic();self.r.connect();self.r.heartbeat();self.assertTrue(self.r.claim())
  self.r.backup=self.r.private/'fixture';self.r.backup.mkdir();self.r.log=self.r.backup/'process.log'
 def tearDown(self):self.r.db.close();self.tmp.cleanup()
 def git(self,*args):return subprocess.check_output(['git',*args],cwd=self.root,stderr=subprocess.DEVNULL).decode().strip()
 def test_root_refused(self):
  with patch.object(u.os,'geteuid',return_value=0),self.assertRaisesRegex(u.Blocked,'root_forbidden'):self.r.basic()
 def test_web_writable_code_refused(self):
  os.chmod(self.root/'bootstrap/cache',0o770)
  with self.assertRaisesRegex(u.Blocked,'ownership'):self.r.basic()
 def test_clean_checkout_passes_preflight(self):self.r.preflight()
 def test_local_changes_preserved(self):
  (self.root/'artisan').write_text('operator changes')
  with self.assertRaisesRegex(u.Blocked,'local_changes'):self.r.preflight()
  self.assertEqual((self.root/'artisan').read_text(),'operator changes')
 def test_untracked_code_blocks(self):
  (self.root/'manual.php').write_text('custom')
  with self.assertRaisesRegex(u.Blocked,'local_changes'):self.r.preflight()
 def test_source_changed_blocks(self):
  self.r.job['source_sha']='f'*40
  with self.assertRaisesRegex(u.Blocked,'source_changed'):self.r.preflight()
 def test_revoked_admin_blocks(self):
  self.r.db.execute('UPDATE users SET is_admin=0')
  with self.assertRaisesRegex(u.Blocked,'authorization'):self.r.preflight()
 def test_existing_running_job_blocks_claim(self):self.assertFalse(self.r.claim())
 def test_pause_owned_by_operator_is_not_deleted(self):
  self.r.pause.write_text('operator');self.r.process();self.assertEqual(self.r.pause.read_text(),'operator')
 def test_backup_integrity_and_environment_preserved(self):
  self.r.backup_data();self.assertEqual(u.digest(self.root/'.env'),u.digest(self.r.backup/'.env'))
  with u.closing(sqlite3.connect(self.r.backup/'database.sqlite'))as c:self.assertEqual(c.execute('PRAGMA integrity_check').fetchone()[0],'ok')
  self.assertEqual(len(json.loads((self.r.backup/'manifest.json').read_text())['sha256']),4)
 def test_maintenance_uses_laravel_json_and_resumes(self):
  with patch.object(u.time,'sleep'):self.r.drain()
  self.assertEqual(json.loads(self.r.down.read_text())['status'],503);self.r.resume();self.assertFalse(self.r.down.exists());self.assertFalse(self.r.pause.exists())
 def test_foreign_maintenance_not_removed(self):
  self.r.down.write_text('{"panel_update":999}');self.r.pause.write_text('999')
  with self.assertRaisesRegex(u.Blocked,'maintenance'):self.r.resume()
  self.assertTrue(self.r.down.exists())
 def test_failure_before_mutation_releases_only_own_maintenance(self):
  with patch.object(self.r,'fetch'),patch.object(u.time,'sleep'),patch.object(self.r,'backup_data',side_effect=u.Blocked('backup')):self.r.process()
  self.assertFalse(self.r.down.exists());self.assertFalse(self.r.pause.exists());self.assertEqual(self.r.db.execute('SELECT status FROM panel_updates').fetchone()[0],'blocked')
 def test_failure_after_mutation_keeps_maintenance_and_database(self):
  def failed_apply():self.r.mutated=True;raise u.Blocked('migrations')
  with patch.object(self.r,'fetch'),patch.object(u.time,'sleep'),patch.object(self.r,'apply',side_effect=failed_apply):self.r.process()
  self.assertTrue(self.r.down.exists());self.assertTrue(self.r.pause.exists());self.assertEqual(self.r.db.execute('SELECT status FROM panel_updates').fetchone()[0],'failed');self.assertEqual(self.r.db.execute('SELECT count(*) FROM users').fetchone()[0],1)
 def test_staged_vendor_swap_and_fast_forward(self):
  with patch.object(u.time,'sleep'):self.r.drain()
  self.r.backup_data();self.r.stage=self.root/'.cache/staged';(self.r.stage/'.cache/vendor').mkdir(parents=True);(self.r.stage/'.cache/vendor/autoload.php').write_text('fixture')
  self.r.apply();self.assertEqual(self.git('rev-parse','HEAD'),self.target);self.assertEqual(self.r.db.execute('SELECT status FROM panel_updates').fetchone()[0],'succeeded');self.assertFalse(self.r.down.exists());self.assertEqual((self.root/'.env').read_text(),'APP_KEY=fixture-preserved\n')
if __name__=='__main__':unittest.main()
