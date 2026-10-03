"""SQLite multiprocess isolation checks; never uses the panel's demo database."""
import os,json,subprocess,time,concurrent.futures,sqlite3,uuid
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1];db=ROOT/'.cache/concurrency.sqlite';db.parent.mkdir(exist_ok=True)
for p in db.parent.glob('concurrency.sqlite*'):p.unlink()
db.touch();env=dict(os.environ,APP_KEY='base64:dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHR0dHQ=',APP_ENV='testing',DB_URL='',DB_CONNECTION='sqlite',DB_DATABASE=str(db),HASH_DRIVER='bcrypt',BCRYPT_ROUNDS='4',QUEUE_CONNECTION='database',LAGOS_TEST_MODE='1')
def run(args,check=True):return subprocess.run(args,cwd=ROOT,env=env,text=True,capture_output=True,check=check)
run(['php','artisan','migrate','--force'])
code="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$u=App\\Models\\User::create(['name'=>'Concurrent','email'=>'concurrent@example.test','password'=>'FixturePassword123!']);for($i=1;$i<=42;$i++)App\\Models\\Invoice::create(['user_id'=>$u->id,'type'=>$i<=22?'deposit':'order','total_minor'=>$i<=22?100:150,'snapshot'=>[],'due_date'=>today()]);"
run(['php','-r',code]);start=time.monotonic()
def call(job):return run(['php','tests/concurrency_worker.php',*map(str,job)],False)
def batch(jobs):
 with concurrent.futures.ThreadPoolExecutor(max_workers=20)as ex:return list(ex.map(call,jobs))
def query(sql):
 with sqlite3.connect(db)as c:return c.execute(sql).fetchone()[0]
results=[]
jobs=[('credit',i,f'payment:{i}')for i in range(1,21)]
r=batch(jobs);results.append({'test':'20 processos creditam depósitos sem perder atualizações','passed':all(x.returncode==0 for x in r) and query('select balance_minor from users where id=1')==2000 and query('select count(*) from payments')==20})
r=batch(jobs);results.append({'test':'Replay concorrente não duplica pagamentos ou saldo','passed':all(x.returncode==0 for x in r) and query('select count(*) from wallet_entries')==20 and query('select balance_minor from users where id=1')==2000})
r=batch([('credit',21,'collision'),('credit',22,'collision')]);results.append({'test':'Uma referência não quita duas faturas concorrentes','passed':sorted(x.returncode for x in r)==[0,2] and query('select balance_minor from users where id=1')==2100})
r=batch([('pay',i)for i in range(23,43)]);results.append({'test':'20 tentativas de gasto não deixam saldo negativo','passed':sum(x.returncode==0 for x in r)==14 and sum(x.returncode==2 for x in r)==6 and query('select balance_minor from users where id=1')==0 and query("select count(*) from payments where gateway='wallet'")==14})

setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();for($i=2;$i<=21;$i++)App\\Models\\User::create(['name'=>'Shopper '.$i,'email'=>'shopper'.$i.'@example.test','password'=>'FixturePassword123!']);App\\Models\\Product::create(['name'=>'Limited','slug'=>'limited','price_minor'=>100,'setup_minor'=>0,'cycle'=>'monthly','stock'=>3]);"
run(['php','-r',setup])
r=batch([('checkout',i,1,str(uuid.uuid4()))for i in range(2,22)])
results.append({'test':'20 compradores competem por 3 unidades sem overselling','passed':sum(x.returncode==0 for x in r)==3 and sum(x.returncode==2 for x in r)==17 and query('select stock from products where id=1')==0 and query('select count(*) from stock_reservations')==3})
with sqlite3.connect(db)as c:c.execute("update invoices set expires_at='2000-01-01 00:00:00' where order_id is not null")
r=batch([('expire',)for i in range(20)])
results.append({'test':'20 expirações concorrentes devolvem estoque uma única vez','passed':all(x.returncode==0 for x in r) and query('select stock from products where id=1')==3 and query("select count(*) from stock_reservations where status='released'")==3})
run(['php','tests/concurrency_worker.php','checkout','2','1',str(uuid.uuid4())]);iid=query('select max(id) from invoices')
r=batch([('credit',iid,'payment-versus-cancel'),('cancel',iid)])
status=query(f'select status from invoices where id={iid}');stock=query('select stock from products where id=1')
results.append({'test':'Pagamento e cancelamento concorrentes têm um único vencedor','passed':sorted(x.returncode for x in r)==[0,2] and ((status=='paid' and stock==2)or(status=='cancelled' and stock==3))})

setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();for($i=0;$i<2;$i++)app(App\\Services\\SupportDesk::class)->open(App\\Models\\User::findOrFail(1),['subject'=>'Concurrent','body'=>'Local test','department'=>'support']);"
run(['php','-r',setup])
r=batch([('support-upload',)for i in range(20)])
results.append({'test':'20 gravações concorrentes respeitam quota de 10 MiB e rollback de respostas rejeitadas','passed':sum(x.returncode==0 for x in r)==10 and sum(x.returncode==2 for x in r)==10 and query('select sum(size) from ticket_attachments where ticket_id=1')==10485760 and query('select count(*) from ticket_replies where ticket_id=1')==10})
r=batch([('support-close',)if i%2 else('support-reply',)for i in range(20)])
results.append({'test':'Fechamentos e respostas concorrentes não ressuscitam chamado encerrado','passed':all(x.returncode in (0,2)for x in r) and all(x.returncode==0 for x in r[1::2]) and query("select status from tickets where id=2")=='closed' and query('select count(*) from ticket_replies where ticket_id=2')==sum(x.returncode==0 for x in r[::2])})

setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();for($i=0;$i<20;$i++)app(App\\Services\\SupportDesk::class)->open(App\\Models\\User::findOrFail(2),['subject'=>'Quota','body'=>'Local test','department'=>'support']);"
run(['php','-r',setup]);r=batch([('support-account-upload',i)for i in range(3,23)])
results.append({'test':'20 chamados simultâneos respeitam quota compartilhada por conta (2 MiB no teste)','passed':sum(x.returncode==0 for x in r)==2 and sum(x.returncode==2 for x in r)==18 and query('select sum(size) from ticket_attachments where ticket_id>=3')==2097152 and query('select count(*) from ticket_replies where ticket_id>=3')==2})

# Native transport is intercepted in-process; no external host is contacted.
whm=ROOT/'.cache/concurrent-whm.json';whm.unlink(missing_ok=True)
setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$c=App\\Models\\Connector::create(['name'=>'Fake WHM','driver'=>'cpanel','endpoint'=>'https://whm-fixture.invalid:2087','token'=>'concurrent-native-token','active'=>true,'settings'=>['username'=>'root','prefix'=>'ct','client_url'=>'https://whm-fixture.invalid:2083']]);$p=App\\Models\\Product::create(['name'=>'Native race','slug'=>'native-race','price_minor'=>100,'setup_minor'=>0,'cycle'=>'monthly','active'=>true,'connector_id'=>$c->id,'provisioning'=>['driver'=>'cpanel','plan'=>'basic','domain_suffix'=>'clients.example.test']]);$i=app(App\\Services\\Checkout::class)->create(App\\Models\\User::findOrFail(1),$p->id,1,(string)Illuminate\\Support\\Str::uuid());app(App\\Services\\Billing::class)->settle($i->id,'test','native-race-payment',100,'BRL');echo json_encode(['service'=>$i->services->first()->id,'operation'=>$i->services->first()->operations()->sole()->id]);"
native=json.loads(run(['php','-r',setup]).stdout);opid=native['operation'];sid=native['service']
r=batch([('native-run',opid)for i in range(20)]);state=json.loads(whm.read_text())
results.append({'test':'20 entregas duplicadas do job criam uma só conta no WHM simulado','passed':all(x.returncode==0 for x in r) and state['calls'].count('createacct')==1 and len(state['calls'])==3 and query(f"select status from operations where id={opid}")=='done' and query(f"select status from services where id={sid}")=='active'})
r=batch([('native-enqueue',sid,i)for i in range(20)]);queued=all(x.returncode==0 for x in r);count=query(f"select count(*) from operations where service_id={sid} and action='suspend'");opid=query(f"select max(id) from operations where service_id={sid}")
r=batch([('native-run',opid)for i in range(20)]);state=json.loads(whm.read_text())
results.append({'test':'20 solicitações e 20 jobs de suspensão produzem uma mutação remota simulada','passed':queued and count==1 and all(x.returncode==0 for x in r) and state['calls'].count('suspendacct')==1 and query(f"select status from services where id={sid}")=='suspended'})

aa=ROOT/'.cache/concurrent-aa.json';aa.unlink(missing_ok=True)
setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$c=App\\Models\\Connector::create(['name'=>'Fake aaPanel','driver'=>'aapanel','endpoint'=>'https://aapanel-fixture.invalid:7800','token'=>'concurrent-aa-key','active'=>true,'settings'=>['prefix'=>'aa']]);$p=App\\Models\\Product::create(['name'=>'aa race','slug'=>'aa-race','price_minor'=>100,'setup_minor'=>0,'cycle'=>'monthly','active'=>true,'connector_id'=>$c->id,'provisioning'=>['driver'=>'aapanel','php_version'=>'82','domain_suffix'=>'sites.example.test']]);$i=app(App\\Services\\Checkout::class)->create(App\\Models\\User::findOrFail(1),$p->id,1,(string)Illuminate\\Support\\Str::uuid());app(App\\Services\\Billing::class)->settle($i->id,'test','aa-race-payment',100,'BRL');echo $i->services->first()->operations()->sole()->id;"
aaid=int(run(['php','-r',setup]).stdout);r=batch([('aapanel-run',aaid)for i in range(20)]);state=json.loads(aa.read_text())
results.append({'test':'20 entregas de job aaPanel criam um único site simulado','passed':all(x.returncode==0 for x in r) and state['calls'].count('AddSite')==1 and len(state['calls'])==4 and query(f"select status from operations where id={aaid}")=='done'})

ptero=ROOT/'.cache/concurrent-ptero.json';ptero.unlink(missing_ok=True)
setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$c=App\\Models\\Connector::create(['name'=>'Ptero fake','driver'=>'pterodactyl','endpoint'=>'https://ptero-fixture.invalid','token'=>'ptero-concurrent-key','active'=>true]);App\\Models\\PterodactylAccount::create(['connector_id'=>$c->id,'user_id'=>1,'remote_user_id'=>41]);$p=App\\Models\\Product::create(['name'=>'Ptero race','slug'=>'ptero-race','price_minor'=>100,'setup_minor'=>0,'cycle'=>'monthly','active'=>true,'connector_id'=>$c->id,'provisioning'=>['driver'=>'pterodactyl','egg'=>1,'location'=>1,'docker_image'=>'fixture:image','startup'=>'run','environment'=>[],'memory'=>1024,'disk'=>10240,'cpu'=>100,'swap'=>0,'io'=>500,'databases'=>0,'allocations'=>1,'backups'=>1]]);$i=app(App\\Services\\Checkout::class)->create(App\\Models\\User::findOrFail(1),$p->id,1,(string)Illuminate\\Support\\Str::uuid());app(App\\Services\\Billing::class)->settle($i->id,'test','ptero-race-payment',100,'BRL');echo $i->services->first()->operations()->sole()->id;"
po=int(run(['php','-r',setup]).stdout);r=batch([('ptero-run',po)for i in range(20)]);state=json.loads(ptero.read_text())
results.append({'test':'20 entregas Pterodactyl criam um único servidor simulado','passed':all(x.returncode==0 for x in r) and state['calls'].count('POST /api/application/servers')==1 and query(f"select status from operations where id={po}")=='done'})
ollama=ROOT/'.cache/concurrent-ollama.json';ollama.unlink(missing_ok=True)
setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();App\\Models\\AiSetting::create(['id'=>1,'endpoint'=>'https://ollama-fixture.invalid','token'=>'concurrent-ollama-key','model'=>'suporte-fixture:small','models'=>['suporte-fixture:small'],'active'=>true,'version'=>1]);echo App\\Models\\AiThread::create(['user_id'=>1])->id;"
tid=int(run(['php','-r',setup]).stdout);key=str(uuid.uuid4());r=batch([('ai-send',tid,key)for i in range(20)])
results.append({'test':'20 envios com mesma chave de mensagem enfileiram uma inferência e debitam uma cota','passed':all(x.returncode==0 for x in r) and query('select count(*) from ai_turns')==1 and query('select requests from ai_daily_usages where user_id=1')==1})
turn=query('select id from ai_turns');r=batch([('ai-answer',turn)for i in range(20)]);state=json.loads(ollama.read_text())
results.append({'test':'20 jobs duplicados de IA produzem uma única chamada Ollama simulada','passed':all(x.returncode==0 for x in r) and state['calls']==['/api/chat'] and query(f"select status from ai_turns where id={turn}")=='done'})
users=ROOT/'.cache/concurrent-ptero-users.json';users.unlink(missing_ok=True)
setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();App\\Models\\User::whereKey(1)->update(['email_verified_at'=>now()]);$c=App\\Models\\Connector::create(['name'=>'Ptero user race','driver'=>'pterodactyl','endpoint'=>'https://ptero-users.invalid','token'=>'ptero-user-concurrent-key','active'=>true]);echo $c->id;"
ucid=int(run(['php','-r',setup]).stdout);r=batch([('ptero-user',ucid)for i in range(20)]);state=json.loads(users.read_text())
results.append({'test':'20 solicitações de conta Pterodactyl produzem um POST e um vínculo, sem duplicar usuário remoto','passed':all(x.returncode in (0,2)for x in r) and any(x.returncode==0 for x in r) and state['posts']==1 and len(state['users'])==1 and query(f'select count(*) from pterodactyl_accounts where connector_id={ucid}')==1 and query(f"select status from pterodactyl_account_requests where connector_id={ucid}")=='done'})

# Paid orders sharing a customer: all account preparations converge without server HTTP.
purchase=ROOT/'.cache/concurrent-purchase-users.json';purchase.unlink(missing_ok=True)
setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();config(['lagos.native_provisioning'=>true]);$c=App\\Models\\Connector::create(['name'=>'Paid account race','driver'=>'pterodactyl','endpoint'=>'https://ptero-users.invalid','token'=>'paid-concurrent-key','active'=>true]);$plan=App\\Models\\Product::where('slug','ptero-race')->firstOrFail()->provisioning;$plan['auto_account']=true;$p=App\\Models\\Product::create(['name'=>'Paid account race','slug'=>'paid-account-race','price_minor'=>100,'setup_minor'=>0,'cycle'=>'monthly','active'=>true,'connector_id'=>$c->id,'provisioning'=>$plan]);$ids=[];for($n=0;$n<20;$n++){$i=app(App\\Services\\Checkout::class)->create(App\\Models\\User::findOrFail(1),$p->id,1,(string)Illuminate\\Support\\Str::uuid());app(App\\Services\\Billing::class)->settle($i->id,'test','paid-account-'.$n,100,'BRL');$op=$i->services->sole()->operations()->sole();$op->update(['status'=>'processing','execution_token'=>(string)Illuminate\\Support\\Str::uuid()]);$ids[]=$op->id;}echo json_encode(['operations'=>$ids,'connector'=>$c->id]);"
fixture=json.loads(run(['php','-r',setup]).stdout)
r=batch([('ptero-purchase-account',i)for i in fixture['operations']])
# Busy queue deliveries are explicitly released in production; this second wave simulates redelivery.
r+=batch([('ptero-purchase-account',i)for i in fixture['operations']]);state=json.loads(purchase.read_text());cid=fixture['connector'];ids=','.join(map(str,fixture['operations']))
results.append({'test':'20 serviços pagos concorrentes compartilham uma conta remota e todos retomam a fila, sem POST duplicado','passed':all(x.returncode==0 for x in r) and state['posts']==1 and len(state['users'])==1 and query(f"select count(*) from operations where id in ({ids}) and status='pending'")==20 and query(f"select count(*) from services where connector_id={cid} and json_extract(provisioning,'$.remote_user_id') is not null")==20})

for driver,port in [('directadmin',2222),('plesk',8443)]:
 file=ROOT/f'.cache/concurrent-{driver}.json';file.unlink(missing_ok=True)
 plan={'domain_suffix':'clients.example.test','ip':'203.0.113.10'}
 plan.update({'plan':'basic'}if driver=='directadmin'else{'owner_id':12,'plan_guid':'01234567-89ab-4cde-8123-456789abcdef'})
 setup="require '.cache/vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$c=App\\Models\\Connector::create(['name'=>'Hosting race','driver'=>'"+driver+"','endpoint'=>'https://"+driver+"-fixture.invalid:"+str(port)+"','token'=>'hosting-race-key','active'=>true,'settings'=>['username'=>'reseller','prefix'=>'lg']]);$p=App\\Models\\Product::create(['name'=>'Hosting race','slug'=>'"+driver+"-race','price_minor'=>100,'setup_minor'=>0,'cycle'=>'monthly','active'=>true,'connector_id'=>$c->id,'provisioning'=>App\\Provisioning\\HostingConfig::product('"+driver+"',['hosting_config'=>'"+json.dumps(plan)+"'])]);$i=app(App\\Services\\Checkout::class)->create(App\\Models\\User::findOrFail(1),$p->id,1,(string)Illuminate\\Support\\Str::uuid());app(App\\Services\\Billing::class)->settle($i->id,'test','"+driver+"-race',100,'BRL');echo $i->services->sole()->operations()->sole()->id;"
 oid=int(run(['php','-r',setup]).stdout);r=batch([('hosting-run',oid)for _ in range(20)]);state=json.loads(file.read_text())
 results.append({'test':f'20 jobs duplicados {driver} criam uma única conta/assinatura simulada','passed':all(x.returncode==0 for x in r) and state['calls'].count('create')==1 and len(state['calls'])==3 and query(f"select status from operations where id={oid}")=='done'})
report={'database':'SQLite WAL / BEGIN IMMEDIATE','workers':20,'seconds':round(time.monotonic()-start,3),'results':results}
(ROOT/'docs/CONCURRENCY-RESULTS.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));print(json.dumps(report,indent=2,ensure_ascii=False));raise SystemExit(any(not r['passed']for r in results))
