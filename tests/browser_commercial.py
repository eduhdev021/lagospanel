"""Real browser checks for the isolated commercial-browser fixture only.
Seed tests/commercial_browser_fixture.php first using APP_ENV=local, LAGOS_TEST_MODE=1
and DB_DATABASE=<root>/.cache/commercial-browser.sqlite. Run that same local server.
"""
import json,os,uuid
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ.get('LAGOS_COMMERCIAL_TEST_URL','http://127.0.0.1:8080')
assert urlparse(BASE).hostname in ('localhost','127.0.0.1'), 'Local fixture only'
F=json.loads((ROOT/'.cache/commercial-browser.json').read_text())
results=[];errors=[];assets=[]
def check(label,ok):
 results.append({'test':label,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+label)
with sync_playwright()as p:
 b=p.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':1000});page=ctx.new_page()
 page.on('pageerror',lambda e:errors.append(str(e)))
 page.on('response',lambda r:assets.append(r.url)if r.status>=400 and r.request.resource_type in ('script','stylesheet','image')else None)
 def go(path):
  r=page.goto(BASE+path);check('HTTP '+path,r.status==200)
 def login(email):
  go('/entrar');page.locator('[name=email]').fill(email);page.locator('[name=password]').fill(F['password']);page.get_by_role('button',name='Entrar',exact=True).click();page.wait_for_url('**/painel')
 def logout():
  go('/painel');page.locator('form[action$="/sair"] button').click()
 login(F['admin']);go('/admin/orcamentos/novo')
 title='Proposta navegador '+uuid.uuid4().hex[:8]
 page.locator('[name=user_id]').fill(str(F['client_id']));page.locator('[name=title]').fill(title)
 page.locator('[name=items_json]').fill('[{"name":"Serviço avulso","quantity":2,"unit_minor":1500}]')
 page.locator('[name=terms]').fill('Execução manual após pagamento. <script>window.quoteXss=1</script>')
 page.get_by_role('button',name='Salvar rascunho').click();page.wait_for_url('**/admin/orcamentos/*');quote_path=page.url.replace(BASE,'').replace('/admin/','/painel/')
 check('Orçamento soma valores no servidor','R$ 30,00' in page.locator('body').inner_text())
 page.get_by_role('button',name='Disponibilizar ao cliente').click()
 check('Orçamento publicado sem executar conteúdo',page.evaluate('window.quoteXss===undefined'))
 go('/admin/avisos');notice='Manutenção navegador '+uuid.uuid4().hex[:8]
 page.locator('[name=title]').fill(notice);page.locator('[name=kind]').select_option('maintenance');page.locator('[name=severity]').select_option('degraded');page.locator('[name=body]').fill('Manutenção fictícia local.');page.locator('[name=published]').check();page.get_by_role('button',name='Criar publicação').click();page.wait_for_url('**/admin/avisos/*');notice_path=page.url.replace(BASE,'').replace('/admin','')
 page.locator('[name=body]').fill('Manutenção encerrada.');page.locator('[name=state]').select_option('resolved');page.get_by_role('button',name='Registrar no histórico').click();check('Histórico de manutenção renderizado','Manutenção encerrada.' in page.locator('body').inner_text())
 go('/admin/downloads');download='Guia navegador '+uuid.uuid4().hex[:8]
 page.locator('[name=title]').fill(download);page.locator('[name=description]').fill('Guia fictício, sem dados reais.');page.locator('[name=file]').set_input_files({'name':'guia.txt','mimeType':'text/plain','buffer':b'conteudo de teste'});page.locator('[name=active]').last.check();page.get_by_role('button',name='Salvar arquivo').click();check('Upload pela interface',download in page.locator('body').inner_text())
 logout();login(F['client']);go(quote_path);page.locator('[name=ack]').check();page.get_by_role('button',name='Aceitar e gerar fatura').click();page.wait_for_url('**/painel/faturas/*');check('Aceite abre fatura correta','R$ 30,00' in page.locator('body').inner_text());invoice_path=page.url.replace(BASE,'')
 go('/painel/downloads');detail=page.locator('details').filter(has=page.get_by_text(download,exact=True));detail.locator('summary').click()
 with page.expect_download()as info:detail.get_by_role('link',name='Baixar arquivo').click()
 d=info.value;check('Download entrega bytes autorizados',Path(d.path()).read_bytes()==b'conteudo de teste')
 for width in [390,768,1440]:
  page.set_viewport_size({'width':width,'height':900})
  for path in [quote_path,invoice_path,'/painel/downloads',notice_path]:
   go(path);check(f'Sem overflow {width} '+path,page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 check('Sem exceções JavaScript',not errors);check('Sem recursos visuais ausentes',not assets)
 b.close()
report={'fixture':'Isolated local SQLite / fake users; no external provider calls','results':results,'js_errors':errors,'failed_assets':assets}
(ROOT/'docs/BROWSER-COMMERCIAL-RESULTS.json').write_text(json.dumps(report,indent=2,ensure_ascii=False))
raise SystemExit(any(not r['passed']for r in results))
