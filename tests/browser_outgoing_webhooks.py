"""Local isolated commercial-browser fixture only; no outbound webhook is sent."""
import json,os,re,uuid
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ.get('LAGOS_COMMERCIAL_TEST_URL','http://127.0.0.1:8080');assert urlparse(BASE).hostname in ('localhost','127.0.0.1')
F=json.loads((ROOT/'.cache/commercial-browser.json').read_text());results=[];errors=[];assets=[]
def check(name,ok):results.append({'test':name,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+name)
with sync_playwright()as p:
 b=p.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':1000});page=ctx.new_page()
 page.on('pageerror',lambda e:errors.append(str(e)))
 page.on('response',lambda r:assets.append(r.url)if r.status>=400 and r.request.resource_type in ('script','stylesheet','image')else None)
 def login(email):
  page.goto(BASE+'/entrar');page.locator('[name=email]').fill(email);page.locator('[name=password]').fill(F['password']);page.get_by_role('button',name='Entrar',exact=True).click();page.wait_for_url('**/painel')
 login(F['admin']);check('Admin abre webhooks',page.goto(BASE+'/admin/webhooks').status==200)
 name='Receiver browser '+uuid.uuid4().hex[:8];form=page.locator('form[action$="/admin/webhooks"]');form.locator('[name=name]').fill(name);form.locator('[name=url]').fill('https://receiver.example.com/hooks');form.locator('[name="events[]"][value="invoice.paid"]').check();form.locator('[name=password]').fill(F['password']);form.locator('[name=ack]').check()
 with page.expect_response(lambda r:r.request.method=='POST'and r.url.endswith('/admin/webhooks'))as response:form.locator('button').click()
 page.wait_for_load_state();check('Criação protegida sem cache','no-store' in response.value.headers.get('cache-control',''));check('Sem Referer na página da chave',response.value.headers.get('referrer-policy')=='no-referrer')
 secret=re.search(r'\b[a-f0-9]{64}\b',page.locator('main').inner_text()).group();check('Chave mostrada na criação',len(secret)==64)
 page.get_by_role('link',name='Voltar aos webhooks').click();check('Chave não reaparece na listagem',secret not in page.content());check('Envio global permanece pausado','OUTGOING_WEBHOOKS_ENABLED=false' in page.locator('body').inner_text())
 detail=page.locator('details').filter(has=page.get_by_text(name+' · Ativo',exact=True));detail.locator('summary').click();detail.locator('[name=password]').fill(F['password']);detail.get_by_role('button',name='Desativar destino').click();check('Desativação pela interface',name+' · Desativado' in page.locator('body').inner_text())
 for width in [390,768,1440]:
  page.set_viewport_size({'width':width,'height':900});check('Sem overflow horizontal '+str(width),page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 page.goto(BASE+'/painel');page.locator('form[action$="/sair"] button').click();login(F['client']);check('Cliente não acessa configuração',page.goto(BASE+'/admin/webhooks').status==403)
 check('Sem erros JavaScript',not errors);check('Sem assets ausentes',not assets);b.close()
(ROOT/'docs/BROWSER-WEBHOOKS-RESULTS.json').write_text(json.dumps({'fixture':'Isolated SQLite; outgoing sending globally disabled','results':results,'js_errors':errors,'failed_assets':assets},indent=2,ensure_ascii=False));raise SystemExit(any(not r['passed']for r in results))
