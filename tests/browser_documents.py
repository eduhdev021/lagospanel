"""Local PDF and canned-reply UI regression. Requires playwright, pypdf and pymupdf."""
import json,os,re,subprocess,uuid,sys,io,ipaddress
from pathlib import Path
from urllib.parse import urlparse
from pypdf import PdfReader
import fitz
from playwright.sync_api import sync_playwright, expect
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8080');F=json.loads((ROOT/'.cache/demo.json').read_text());suffix=uuid.uuid4().hex[:8]
assert urlparse(BASE).scheme=='http' and (urlparse(BASE).hostname=='localhost' or ipaddress.ip_address(urlparse(BASE).hostname).is_loopback)
(ROOT/'docs/BROWSER-DOCUMENTS-RESULTS.json').unlink(missing_ok=True)
def fixture(file,*args):return subprocess.run(['php','tests/'+file,*map(str,args)],cwd=ROOT,env=dict(os.environ,LAGOS_TEST_MODE='1'),capture_output=True,text=True,check=True).stdout
D=json.loads(fixture('documents-fixture.php'));staff=json.loads(fixture('support-fixture.php'))
results=[];errors=[]
def check(label,ok):
 results.append({'test':label,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+label)
with sync_playwright()as pw:
 b=pw.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':1000},extra_http_headers={'X-Forwarded-For':'192.0.2.46'});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 def go(path):
  r=p.goto(BASE+path);check('HTTP '+path,r.status==200);return r
 def login(email,password):
  go('/entrar');p.locator('[name=email]').fill(email);p.locator('[name=password]').fill(password);p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel')
 def logout():p.locator('form[action$="/sair"] button').click()
 login(F['client'],F['password']);go(f"/painel/faturas/{D['invoice']}")
 with p.expect_download()as downloaded:p.get_by_role('link',name='Baixar fatura em PDF').click()
 dl=downloaded.value;path=ROOT/'.cache/invoice-alpha6.pdf';dl.save_as(path);data=path.read_bytes();reader=PdfReader(io.BytesIO(data));text='\n'.join(page.extract_text()for page in reader.pages)
 check('Download é PDF real com nome seguro',data.startswith(b'%PDF-') and dl.suggested_filename==f"fatura-{D['invoice']}.pdf")
 check('PDF preserva acentos, total e último item em múltiplas páginas',len(reader.pages)>1 and 'FIMDOSITENS' in text and 'R$ 60,00' in text and 'Ação' in text)
 check('PDF identifica documento não fiscal e não executa HTML','não é nota fiscal' in text and '<script>' in text and not any(page.get('/Annots')for page in reader.pages))
 check('Renderização de todas as páginas PDF',all(len(pg.get_pixmap(matrix=fitz.Matrix(.3,.3)).samples)>0 for pg in fitz.open(path)))
 doc=fitz.open(path);doc[0].get_pixmap().save(str(ROOT/'.cache/invoice-alpha6-preview.png'))
 r=ctx.request.get(BASE+f"/painel/faturas/{D['invoice']}/pdf");check('PDF autenticado não permite cache','no-store'in r.headers.get('cache-control','') and r.headers.get('x-content-type-options')=='nosniff')
 r=ctx.request.get(BASE+f"/painel/faturas/{D['other']}/pdf");check('PDF de outro titular retorna 404',r.status==404);logout()
 login(staff['writer']['email'],staff['writer']['password']);go('/admin/suporte/modelos');title='Modelo '+suffix;body='Olá! Ação de suporte.\n<script>window.template_xss=1</script>'
 form=p.locator('form[action$="/admin/suporte/modelos"]');form.locator('[name=title]').fill(title);form.locator('[name=body]').fill(body);form.locator('[name=department]').select_option('support');form.get_by_role('button',name='Salvar resposta pronta').click();check('Modelo salvo pela equipe','Resposta pronta salva' in p.locator('body').inner_text());row=p.locator('tr').filter(has_text=title);edit=row.get_by_role('link',name='Editar').get_attribute('href');tid=int(re.search(r'/modelos/(\d+)/',edit)[1])
 go(f"/admin/suporte/{D['ticket']}");form=p.locator(f"form[action$='/admin/suporte/{D['ticket']}']");form.locator('textarea[name=body]').fill('Rascunho preservado');form.get_by_label('Resposta pronta',exact=True).select_option(label=title);form.get_by_role('button',name='Inserir no rascunho').click();expect(form.locator('[data-canned-status]')).to_contain_text('Modelo inserido')
 check('Inserção preserva rascunho e trata HTML como texto',form.locator('textarea[name=body]').input_value()=='Rascunho preservado\n\n'+body and not p.evaluate('window.template_xss'))
 check('Inserção não envia mensagem',int(fixture('documents-fixture.php','count',D['ticket']))==0)
 p.set_viewport_size({'width':390,'height':900});check('Atendimento sem overflow móvel',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.set_viewport_size({'width':1440,'height':1000})
 form.get_by_role('button',name='Enviar resposta',exact=True).click();check('Envio explícito registra resposta',int(fixture('documents-fixture.php','count',D['ticket']))==1 and not p.evaluate('window.template_xss'))
 go(f'/admin/suporte/modelos/{tid}/editar');p.locator('[name=active]').uncheck();p.get_by_role('button',name='Salvar resposta pronta').click();go(f"/admin/suporte/{D['ticket']}");check('Modelo desativado sai do seletor',not p.locator('[data-canned-reply] option').filter(has_text=title).count())
 r=ctx.request.get(BASE+f"/admin/suporte/{D['ticket']}/modelos/{tid}");check('URL antiga não carrega modelo desativado',r.status==404);logout()
 login(staff['viewer']['email'],staff['viewer']['password']);go('/admin/suporte/modelos');check('Equipe somente leitura não recebe formulário',not p.get_by_role('button',name='Salvar resposta pronta').count());r=ctx.request.get(BASE+f"/admin/faturas/{D['invoice']}/pdf");check('Permissão de suporte não autoriza PDF financeiro',r.status==403);logout()
 login(F['client'],F['password']);go(f"/painel/suporte/{D['ticket']}");check('Cliente recebe texto escapado, sem biblioteca',not p.evaluate('window.template_xss') and 'Rascunho preservado' in p.locator('body').inner_text() and not p.locator('[data-canned-reply]').count());check('Sem erros JavaScript',not errors);b.close()
report={'results':results,'javascript_errors':errors,'pdf_pages':len(reader.pages),'external_providers':'none'};(ROOT/'docs/BROWSER-DOCUMENTS-RESULTS.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));sys.exit(any(not x['passed']for x in results))
