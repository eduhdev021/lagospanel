"""Native UI integration with an in-process WHM stub. No real WHM requests. A reserved test IP isolates the login rate bucket on the LOCAL trusted-proxy demo."""
import json,os,re,subprocess,uuid,sys,hashlib
from pathlib import Path
from urllib.parse import urlparse
import ipaddress
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8080');F=json.loads((ROOT/'.cache/demo.json').read_text());suffix=uuid.uuid4().hex[:8]
assert urlparse(BASE).scheme=='http' and (urlparse(BASE).hostname=='localhost' or ipaddress.ip_address(urlparse(BASE).hostname).is_loopback), 'Native UI fixtures are local-only'
(ROOT/'docs/BROWSER-NATIVE-RESULTS.json').unlink(missing_ok=True)
results=[];errors=[];assets=[]
def check(label,ok):results.append({'test':label,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+label)
def process(sid):return json.loads(subprocess.run(['php','tests/native-fixture.php',str(sid)],cwd=ROOT,env=dict(os.environ,LAGOS_TEST_MODE='1'),capture_output=True,text=True,check=True).stdout)
with sync_playwright()as pw:
 b=pw.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':1000},extra_http_headers={'X-Forwarded-For':'192.0.2.44'});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.on('response',lambda r:assets.append(r.url)if r.status>=400 and r.request.resource_type in('script','stylesheet','image')else None)
 def go(path):
  r=p.goto(BASE+path);check('HTTP '+path,r.status==200);return r
 def login(email):
  go('/entrar');p.locator('[name=email]').fill(email);p.locator('[name=password]').fill(F['password']);p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel')
 def logout():p.locator('form[action$="/sair"] button').click()
 login(F['admin']);go('/admin/integracoes');form=p.locator('form[action$="/admin/integracoes"]');name='WHM Alpha4 '+suffix;form.locator('[name=name]').fill(name);form.locator('[name=driver]').select_option('cpanel');form.locator('[name=endpoint]').fill('https://whm-fixture.invalid:2087');form.locator('[name=token]').fill('fake-native-token-'+suffix);form.locator('details summary').click();form.locator('[name=whm_username]').fill('root');form.locator('[name=account_prefix]').fill('nt');form.locator('[name=client_url]').fill('https://whm-fixture.invalid:2083');form.locator('[name=active]').check();form.locator('[name=ack_native]').check();form.get_by_role('button',name='Cadastrar integração',exact=True).click();check('Driver WHM cadastrado sem exibir token',name in p.locator('body').inner_text() and 'fake-native-token-'+suffix not in p.content());check('Bloqueio global visível','Bloqueadas pela configuração local' in p.locator('body').inner_text())
 p.set_viewport_size({'width':390,'height':900});check('Integrações sem overflow móvel',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.set_viewport_size({'width':1440,'height':1000})
 go('/admin/produtos');form=p.locator('form[action$="/admin/produtos"]');product='Hospedagem nativa '+suffix;form.locator('[name=name]').fill(product);form.locator('[name=slug]').fill('native-'+suffix);form.locator('[name=price]').fill('12,00');form.locator('[name=connector_id]').select_option(label=name);form.locator('[name=cpanel_plan]').fill('basic');form.locator('[name=cpanel_domain_suffix]').fill('clients.example.test');form.get_by_role('button',name='Salvar produto',exact=True).click();check('Pacote nativo salvo','Produto salvo' in p.locator('body').inner_text());row=p.locator('tr').filter(has_text=product);pid=int(re.search(r'/produtos/(\d+)/',row.get_by_role('link',name='Editar',exact=True).get_attribute('href'))[1]);logout()
 login(F['client']);go('/loja');form=p.locator(f'form:has(input[name=product_id][value="{pid}"])');n=1
 while not form.count() and n<50:
  n+=1;go('/loja?page='+str(n));form=p.locator(f'form:has(input[name=product_id][value="{pid}"])')
 form.get_by_role('button',name='Adicionar ao carrinho',exact=True).click();p.wait_for_url('**/painel/carrinho');p.get_by_role('button',name='Finalizar pedido',exact=True).click();p.wait_for_url('**/painel/faturas/*');iid=int(p.url.rsplit('/',1)[-1]);check('Pedido nativo gera fatura','R$ 12,00' in p.locator('.invoice-total').inner_text());logout()
 login(F['admin']);go('/admin/faturas');form=p.locator(f'form[action$="/faturas/{iid}/confirmar"]');form.locator('[name=note]').fill('Recebimento fictício — integração simulada');form.locator('button').click();go('/admin/servicos');card=p.locator('.card').filter(has=p.get_by_role('heading',name=re.compile(re.escape(product))));form=card.locator('form');sid=int(re.search(r'/servicos/(\d+)',form.get_attribute('action'))[1]);created=process(sid);p.reload();check('Worker nativo ativa serviço via contrato simulado',created['status']=='active' and created['calls']==['listaccts','createacct','listaccts']);logout()
 login(F['client']);go('/painel/servicos');details=p.locator('details').filter(has_text=product);details.locator('summary').click();details.locator('[name=password]').fill(F['password']);details.get_by_role('button',name='Ver acesso inicial',exact=True).click();codes=p.locator('code').all_text_contents();check('Cliente recupera credencial inicial autenticado',len(codes)==2 and hashlib.sha256(codes[1].encode()).hexdigest()==created['password_hash']);secret=codes[1];p.set_viewport_size({'width':390,'height':900});check('Credenciais sem overflow móvel',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.set_viewport_size({'width':1440,'height':1000});check('Link aponta para cPanel configurado',p.get_by_role('link',name='Abrir cPanel',exact=True).get_attribute('href')=='https://whm-fixture.invalid:2083');p.get_by_role('link',name='Voltar aos serviços',exact=True).click();check('Senha não aparece na listagem',secret not in p.content());logout()
 login(F['admin']);go('/admin/servicos');card=p.locator('.card').filter(has=p.get_by_role('heading',name=re.compile(re.escape(product))))
 for state,expected in [('suspended','suspended'),('active','active'),('cancelled','cancelled')]:
  form=card.locator('form');form.locator('[name=status]').select_option(state);form.locator('[name=note]').fill('Ação em WHM simulado — sem servidor real')
  if state=='cancelled':form.locator('[name=confirm_termination]').check()
  form.get_by_role('button',name='Confirmar ação',exact=True).click();data=process(sid);p.reload();check('Ciclo remoto '+state,data['status']==expected)
 go('/admin/operacoes');check('Sem exceções JavaScript',not errors);check('Sem assets ausentes',not assets);b.close()
(ROOT/'docs/BROWSER-NATIVE-RESULTS.json').write_text(json.dumps({'results':results,'js_errors':errors,'failed_assets':assets},ensure_ascii=False,indent=2));print(f'{sum(x["passed"]for x in results)} passed / {sum(not x["passed"]for x in results)} failed');sys.exit(any(not x['passed']for x in results))
