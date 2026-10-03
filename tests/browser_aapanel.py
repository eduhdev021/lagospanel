"""LOCAL aaPanel UI check with intercepted HTTP. No real aaPanel contacted."""
import json,os,re,subprocess,uuid,sys
from pathlib import Path
from urllib.parse import urlparse
import ipaddress
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8080');F=json.loads((ROOT/'.cache/demo.json').read_text());suffix=uuid.uuid4().hex[:8]
assert urlparse(BASE).scheme=='http' and (urlparse(BASE).hostname=='localhost' or ipaddress.ip_address(urlparse(BASE).hostname).is_loopback)
(ROOT/'docs/BROWSER-AAPANEL-RESULTS.json').unlink(missing_ok=True)
results=[];errors=[]
def check(label,ok):results.append({'test':label,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+label)
def process(sid):return json.loads(subprocess.run(['php','tests/aapanel-fixture.php',str(sid)],cwd=ROOT,env=dict(os.environ,LAGOS_TEST_MODE='1'),capture_output=True,text=True,check=True).stdout)
with sync_playwright()as pw:
 b=pw.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':1000},extra_http_headers={'X-Forwarded-For':'192.0.2.45'});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 def go(path):r=p.goto(BASE+path);check('HTTP '+path,r.status==200);return r
 def login(email):
  go('/entrar');p.locator('[name=email]').fill(email);p.locator('[name=password]').fill(F['password']);p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel')
 def logout():p.locator('form[action$="/sair"] button').click()
 login(F['admin']);go('/admin/integracoes');form=p.locator('form[action$="/admin/integracoes"]');name='aaPanel '+suffix;form.locator('[name=name]').fill(name);form.locator('[name=driver]').select_option('aapanel');form.locator('[name=endpoint]').fill('https://aapanel-fixture.invalid:7800');form.locator('[name=token]').fill('fake-aa-secret-'+suffix);form.locator('details summary').click();form.locator('[name=account_prefix]').fill('aa');form.locator('[name=active]').check();form.locator('[name=ack_native]').check();form.get_by_role('button',name='Cadastrar integração',exact=True).click();check('aaPanel cadastrado sem campos WHM',name in p.locator('body').inner_text());check('Chave aaPanel não exibida','fake-aa-secret-'+suffix not in p.content());p.set_viewport_size({'width':390,'height':900});check('Integrações móveis sem overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.set_viewport_size({'width':1440,'height':1000})
 go('/admin/produtos');form=p.locator('form[action$="/admin/produtos"]');product='Site aaPanel '+suffix;form.locator('[name=name]').fill(product);form.locator('[name=slug]').fill('aa-'+suffix);form.locator('[name=price]').fill('9,00');form.locator('[name=connector_id]').select_option(label=name);form.locator('[name=aapanel_domain_suffix]').fill('sites.example.test');form.locator('[name=aapanel_php_version]').fill('82');form.get_by_role('button',name='Salvar produto',exact=True).click();check('Produto aaPanel salvo','Produto salvo' in p.locator('body').inner_text());row=p.locator('tr').filter(has_text=product);pid=int(re.search(r'/produtos/(\d+)/',row.get_by_role('link',name='Editar',exact=True).get_attribute('href'))[1]);logout()
 login(F['client']);go('/loja');form=p.locator(f'form:has(input[name=product_id][value="{pid}"])');n=1
 while not form.count() and n<50:
  n+=1;go('/loja?page='+str(n));form=p.locator(f'form:has(input[name=product_id][value="{pid}"])')
 form.get_by_role('button',name='Adicionar ao carrinho',exact=True).click();p.wait_for_url('**/painel/carrinho');p.get_by_role('button',name='Finalizar pedido',exact=True).click();p.wait_for_url('**/painel/faturas/*');iid=int(p.url.rsplit('/',1)[-1]);check('Fatura aaPanel pelo preço contratado','R$ 9,00' in p.locator('.invoice-total').inner_text());logout()
 login(F['admin']);go('/admin/faturas');form=p.locator(f'form[action$="/faturas/{iid}/confirmar"]');form.locator('[name=note]').fill('Teste de aaPanel simulado; sem recebimento real');form.locator('button').click();go('/admin/servicos');card=p.locator('.card').filter(has=p.get_by_role('heading',name=re.compile(re.escape(product))));sid=int(re.search(r'/servicos/(\d+)',card.locator('form').get_attribute('action'))[1]);data=process(sid);check('Criação de site confirmada pelo worker',data['status']=='active' and data['remote_id'].isdigit() and data['calls']==['getData','GetPHPVersion','AddSite','getData']);p.reload();logout()
 login(F['client']);go('/painel/servicos');details=p.locator('details').filter(has_text=product);details.locator('summary').click();check('Cliente vê site gerenciado sem login de administrador','sem acesso administrativo aaPanel' in details.inner_text() and not details.get_by_role('button',name='Ver acesso inicial',exact=True).count());logout()
 login(F['admin']);go('/admin/servicos');card=p.locator('.card').filter(has=p.get_by_role('heading',name=re.compile(re.escape(product))))
 for state in ['suspended','active','cancelled']:
  form=card.locator('form');form.locator('[name=status]').select_option(state);form.locator('[name=note]').fill('Ciclo aaPanel em transporte simulado')
  if state=='cancelled':check('Encerramento informa preservação de arquivos','preservando arquivos' in card.inner_text());form.locator('[name=confirm_termination]').check()
  form.get_by_role('button',name='Confirmar ação',exact=True).click();data=process(sid);p.reload();check('aaPanel '+state,data['status']==state)
 go('/admin/operacoes');check('Sem exceções JavaScript',not errors);b.close()
(ROOT/'docs/BROWSER-AAPANEL-RESULTS.json').write_text(json.dumps({'results':results,'js_errors':errors},ensure_ascii=False,indent=2));print(f'{sum(x["passed"]for x in results)} passed / {sum(not x["passed"]for x in results)} failed');sys.exit(any(not x['passed']for x in results))
