"""Client power and SSO: real HTTP/CSRF/Blade, outbound API fake, isolated installation only."""
import os,json,sqlite3,ipaddress
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];COPY=Path(os.environ['LAGOS_CONTROLS_TEST_ROOT']).resolve();BASE=os.environ['LAGOS_TEST_URL']
assert os.environ.get('LAGOS_TEST_MODE')=='1' and COPY!=ROOT and '/.cache/'in str(COPY) and ipaddress.ip_address(urlparse(BASE).hostname).is_loopback
F=json.loads((COPY/'.cache/controls-browser.json').read_text());mode=COPY/'.cache/controls-mode';trace=COPY/'.cache/controls-calls.jsonl';trace.unlink(missing_ok=True)
results=[];errors=[]
def check(label,ok):results.append({'test':label,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+label)
def calls():return [json.loads(x)for x in trace.read_text().splitlines()]if trace.exists()else[]
def posts():return sum(c['path'].endswith('/power')for c in calls())
def status():
 with sqlite3.connect(COPY/'database/database.sqlite')as d:return d.execute('select status from pterodactyl_controls order by id desc limit 1').fetchone()[0]
with sync_playwright()as pw:
 b=pw.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1365,'height':1000});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 p.goto(BASE+'/entrar');p.locator('[name=email]').fill(F['email']);p.locator('[name=password]').fill(F['password']);p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel');p.goto(BASE+'/painel/servicos')
 check('Controles aparecem em serviços ativos',p.get_by_role('button',name='Entrar no cPanel',include_hidden=True).count()==1 and p.get_by_role('button',name='Enviar comando',include_hidden=True).count()==1)
 power='/painel/servicos/'+str(F['pterodactyl'])+'/energia';sso='/painel/servicos/'+str(F['cpanel'])+'/acesso-cpanel'
 check('CSRF bloqueia energia sem token',ctx.request.post(BASE+power,form={'signal':'restart'}).status==419)
 check('CSRF bloqueia SSO sem token',ctx.request.post(BASE+sso,form={}).status==419)
 def power_form():
  p.locator('details').evaluate_all('(els)=>els.forEach(e=>e.open=true)');return p.locator('form[action$="/energia"]')
 def submit_power():
  f=power_form();data={n:f.locator('[name='+n+']').input_value()for n in ['_token','request_key']};data.update(token='ptlc_browser-client-key-123456',password=F['password'],signal='restart',ack='1')
  f.locator('[name=token]').fill(data['token']);f.locator('[name=password]').fill(F['password']);f.locator('[name=signal]').select_option('restart');f.locator('[name=ack]').check();f.get_by_role('button',name='Enviar comando').click();return data
 mode.write_text('normal');data=submit_power();check('Comando aceito por HTTP e persistido',status()=='accepted' and posts()==1 and 'Comando aceito pelo Pterodactyl' in p.locator('body').inner_text())
 check('Chave e senha não voltam ao HTML',data['token']not in p.content() and F['password']not in p.content())
 ctx.request.post(BASE+power,form=data);check('Reenvio HTTP não duplica comando',posts()==1)
 mode.write_text('wronguser');submit_power();check('Conta remota diferente bloqueada sem comando',status()=='failed' and posts()==1)
 mode.write_text('timeout');submit_power();check('Timeout fica incerto sem retry',status()=='uncertain' and posts()==2)
 p.goto(BASE+'/painel/servicos');p.locator('details').evaluate_all('(els)=>els.forEach(e=>e.open=true)');f=p.locator('form[action$="/acesso-inicial"]');mode.write_text('unsafe');f.locator('[name=password]').fill(F['password']);f.get_by_role('button',name='Entrar no cPanel').click();check('SSO rejeita origem externa',p.url.startswith(BASE) and 'Acesso temporário não confirmado' in p.locator('body').inner_text())
 p.locator('details').evaluate_all('(els)=>els.forEach(e=>e.open=true)');f=p.locator('form[action$="/acesso-inicial"]');mode.write_text('normal');f.locator('[name=password]').fill(F['password'])
 p.route('https://whm-browser.invalid:2083/**',lambda r:r.fulfill(status=200,content_type='text/html',body='<p>Provider session fixture</p>'))
 with p.expect_response(lambda r:r.url==BASE+sso)as received:f.get_by_role('button',name='Entrar no cPanel').click()
 response=received.value;check('SSO emite 303 para origem validada',response.status==303 and response.headers.get('location','').startswith('https://whm-browser.invalid:2083/cpsess1234567890/login/'))
 check('SSO envia no-store e no-referrer','no-store'in response.headers.get('cache-control','') and response.headers.get('referrer-policy')=='no-referrer')
 p.close();p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.goto(BASE+'/painel/servicos');p.set_viewport_size({'width':390,'height':900});p.locator('details').evaluate_all('(els)=>els.forEach(e=>e.open=true)');check('Controles sem overflow móvel',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 check('Nenhum erro JavaScript',not errors);b.close()
(ROOT/'docs/BROWSER-CONTROLS-RESULTS.json').write_text(json.dumps({'results':results,'javascript_errors':errors,'provider':'simulated outbound API; real browser, HTTP kernel, CSRF and database'},indent=2,ensure_ascii=False));raise SystemExit(any(not r['passed']for r in results))
