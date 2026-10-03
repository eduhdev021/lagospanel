"""Real HTTP/CSRF/Blade browser test against tests/ptero-users-router.php; external API simulated."""
import os,json,sqlite3,ipaddress
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8083')
assert os.environ.get('LAGOS_TEST_MODE')=='1' and ipaddress.ip_address(urlparse(BASE).hostname).is_loopback
F=json.loads((ROOT/'.cache/ptero-users-browser-credentials.json').read_text());mode=ROOT/'.cache/ptero-users-browser-mode';remote=ROOT/'.cache/ptero-users-browser-remote.json'
results=[];errors=[]
def check(name,value):results.append({'test':name,'passed':bool(value)});print(('PASS 'if value else'FAIL ')+name)
def state():return json.loads(remote.read_text())
with sync_playwright()as pw:
 b=pw.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1365,'height':1000});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 p.goto(BASE+'/entrar');p.locator('[name=email]').fill(F['email']);p.locator('[name=password]').fill(F['password']);p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel')
 path='/admin/integracoes/'+str(F['connector'])+'/contas';r=p.goto(BASE+path);check('Tela de contas com criação e vínculo manual',r.status==200 and p.get_by_role('button',name='Criar e vincular conta remota').count()==1 and p.get_by_role('button',name='Vincular conta',exact=True).count()==1)
 check('CSRF bloqueia criação sem token',ctx.request.post(BASE+path+'/criar-remota',form={'user_id':F['client1'],'first_name':'Cliente','last_name':'Teste','ack':'1'}).status==419)
 def create(client):
  form=p.locator('form[action$="/criar-remota"]').first;form.locator('[name=user_id]').fill(str(client));form.locator('[name=first_name]').fill('Cliente');form.locator('[name=last_name]').fill('Teste');form.locator('[name=ack]').check();form.get_by_role('button',name='Criar e vincular conta remota').click()
 mode.write_text('normal');create(F['client1']);check('Criação HTTP e vínculo automático após leitura','Conta remota confirmada e vinculada' in p.locator('body').inner_text() and state()['posts']==1)
 check('Chave e senha local ausentes do HTML','ptero-browser-fake-key' not in p.content() and F['password'] not in p.content())
 create(F['client1']);check('Reenvio do formulário não duplica usuário',state()['posts']==1 and len(state()['users'])==1)
 mode.write_text('timeout');create(F['client2']);check('Timeout após criação mostra revisão sem vínculo falso','Resultado pendente de revisão' in p.locator('body').inner_text() and state()['posts']==2)
 with sqlite3.connect(ROOT/'.cache/ptero-users-browser.sqlite')as db:
  rid=db.execute('select id from pterodactyl_account_requests where user_id=?',(F['client2'],)).fetchone()[0]
  check('Timeout preserva tentativa, mas não libera conta local',db.execute('select count(*) from pterodactyl_accounts where user_id=?',(F['client2'],)).fetchone()[0]==0)
 mode.write_text('normal');p.locator('form[action$="/solicitacoes/'+str(rid)+'/conferir"]').get_by_role('button',name='Conferir resultado').click();check('Conferência recupera conta sem repetir POST','Conta remota confirmada e vinculada' in p.locator('body').inner_text() and state()['posts']==2)
 with sqlite3.connect(ROOT/'.cache/ptero-users-browser.sqlite')as db:check('Ambos os vínculos confirmados em banco',db.execute('select count(*) from pterodactyl_accounts').fetchone()[0]==2)
 check('Aviso de senha via provedor sem promessa de entrega','confira o SMTP daquele painel' in p.locator('body').inner_text())
 p.set_viewport_size({'width':390,'height':900});check('Tela sem overflow móvel',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 check('Nenhum erro JavaScript',not errors);b.close()
(ROOT/'docs/BROWSER-PTERO-USERS-RESULTS.json').write_text(json.dumps({'results':results,'javascript_errors':errors,'provider':'Http::fake only; real HTTP application, CSRF, database and browser'},indent=2,ensure_ascii=False));raise SystemExit(any(not r['passed']for r in results))
