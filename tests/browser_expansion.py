"""Alpha.2 UI checks against the isolated LOCAL demo. Does not use live providers."""
import json,os,re,subprocess,uuid,sys
from pathlib import Path
from urllib.parse import urlparse
import ipaddress
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8080');F=json.loads((ROOT/'.cache/demo.json').read_text());suffix=uuid.uuid4().hex[:8]
assert urlparse(BASE).scheme=='http' and (urlparse(BASE).hostname=='localhost' or ipaddress.ip_address(urlparse(BASE).hostname).is_loopback), 'Fixtures are local-only'
(ROOT/'docs/BROWSER-EXPANSION-RESULTS.json').unlink(missing_ok=True)
r=subprocess.run(['php','tests/expansion-fixture.php'],cwd=ROOT,env=dict(os.environ,LAGOS_TEST_MODE='1'),capture_output=True,text=True,check=True);operator=json.loads(r.stdout)
results=[];errors=[];assets=[]
def check(label,ok):results.append({'test':label,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+label)
with sync_playwright()as pw:
 b=pw.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':1000},extra_http_headers={'X-Forwarded-For':'192.0.2.42'});page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.on('response',lambda r:assets.append(r.url)if r.status>=400 and r.request.resource_type in('script','stylesheet','image')else None)
 def go(path):
  r=page.goto(BASE+path);check('HTTP '+path,r.status==200);return r
 def login(email,password):
  go('/entrar');page.locator('[name=email]').fill(email);page.locator('[name=password]').fill(password);page.get_by_role('button',name='Entrar',exact=True).click();page.wait_for_url('**/painel')
 def logout():page.locator('form[action$="/sair"] button').click()
 def product(name,price,setup):
  go('/admin/produtos');page.locator('[name=name]').fill(name);page.locator('[name=slug]').fill(name.lower().replace(' ','-'));page.locator('[name=price]').fill(price);page.locator('[name=setup]').fill(setup);page.locator('[name=stock]').fill('5');page.locator('[name=max_per_user]').fill('3');page.get_by_role('button',name='Salvar produto').click();check('Produto criado: '+name,'Produto salvo' in page.locator('body').inner_text());row=page.locator('tr').filter(has_text=name);url=row.get_by_role('link',name='Opções',exact=True).get_attribute('href');return int(re.search(r'/produtos/(\d+)/',url)[1])
 login(F['admin'],F['password']);a=product('Alpha2 Config '+suffix,'30,00','5,00');c=product('Alpha2 Base '+suffix,'20,00','0,00')
 go(f'/admin/produtos/{a}/opcoes');page.locator('[name=name]').fill('Memória');page.get_by_role('button',name='Criar opção',exact=True).click();check('Grupo de opção criado','Opção criada' in page.locator('body').inner_text());page.locator('[name=label]').fill('8 GB');page.locator('[name=recurring]').fill('10,00');page.locator('[name=setup]').fill('2,00');page.get_by_role('button',name='Adicionar valor',exact=True).click();check('Valor configurável cadastrado','Valor adicionado' in page.locator('body').inner_text())
 go('/admin/cupons');coupon='A2'+suffix.upper();page.locator('[name=code]').fill(coupon);page.locator('[name=kind]').select_option('fixed');page.locator('[name=fixed]').fill('15,00');page.locator('[name=max_uses]').fill('2');page.locator('[name=per_user_limit]').fill('1');page.get_by_role('button',name='Criar cupom',exact=True).click();check('Cupom fixo criado','Cupom criado' in page.locator('body').inner_text())
 go('/admin/conhecimento');slug='guia-'+suffix;page.locator('[name=title]').fill('Guia '+suffix);page.locator('[name=slug]').fill(slug);page.locator('[name=category]').fill('Primeiros passos');page.locator('[name=body]').fill('<script>window.kb_xss=1</script>\nConteúdo de ajuda.');page.locator('[name=published]').check();page.get_by_role('button',name='Salvar artigo').click();check('Artigo publicado','Artigo salvo' in page.locator('body').inner_text())
 go('/admin/equipe');role='Financeiro Leitura '+suffix;page.locator('[name=name]').fill(role);page.locator('input[name="permissions[]"][value="billing.view"]').check();page.get_by_role('button',name='Criar função',exact=True).click();check('Função granular criada','Função criada' in page.locator('body').inner_text())
 # Locate the newly created operator, including pagination when the demo has many prior test accounts.
 found=False
 for n in range(1,20):
  go('/admin/equipe?page='+str(n));row=page.locator('tr').filter(has_text=operator['email'])
  if row.count():found=True;break
  if not page.get_by_role('link',name='Próxima',exact=True).count():break
 if not found:raise AssertionError('Operador não encontrado')
 row.locator('select[name=staff_role_id]').select_option(label=role);row.get_by_role('button',name='Aplicar',exact=True).click();check('Função atribuída','Permissões atualizadas' in page.locator('body').inner_text());logout()
 login(F['client'],F['password'])
 for pid,qty in [(a,2),(c,1)]:
  go('/loja');form=page.locator('form').filter(has=page.locator(f'input[name=product_id][value="{pid}"]'))
  # Products paginate; locate the newly-created plan on the corresponding page.
  n=1
  while not form.count() and n<20:
   n+=1;go('/loja?page='+str(n));form=page.locator('form').filter(has=page.locator(f'input[name=product_id][value="{pid}"]'))
  form.locator('[name=quantity]').fill(str(qty));form.get_by_role('button',name='Adicionar ao carrinho',exact=True).click();page.wait_for_url('**/painel/carrinho');check('Item no carrinho '+str(pid),'Item adicionado' in page.locator('body').inner_text())
 check('Subtotal multiproduto correto','R$ 114,00' in page.locator('body').inner_text());page.set_viewport_size({'width':390,'height':900});check('Carrinho móvel sem overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth+1'));page.set_viewport_size({'width':1440,'height':1000});page.locator('[name=coupon]').fill(coupon);page.get_by_role('button',name='Finalizar pedido',exact=True).click();page.wait_for_url('**/painel/faturas/*');invoice=int(page.url.rsplit('/',1)[-1]);check('Cupom e opções calculados na fatura','R$ 99,00' in page.locator('.invoice-total').inner_text());check('Configuração aparece na fatura','Memória: 8 GB' in page.locator('body').inner_text());check('Prazo de reserva visível','Reserva válida até' in page.locator('body').inner_text());page.get_by_role('button',name='Cancelar pedido não pago',exact=True).click();check('Cancelamento antes do pagamento','Pedido cancelado e reservas liberadas' in page.locator('body').inner_text())
 go('/painel/api');tokenname='Token Browser '+suffix;page.locator('[name=name]').fill(tokenname);page.locator('[name=days]').fill('1');page.locator('input[name="scopes[]"][value="services:read"]').check();page.locator('[name=password]').fill(F['password']);page.get_by_role('button',name='Criar token',exact=True).click();token=page.locator('code').filter(has_text=re.compile('^lp_')).inner_text();check('Token exibido após criação',token.startswith('lp_'));headers={'Authorization':'Bearer '+token};response=ctx.request.get(BASE+'/api/v1/services',headers=headers);check('API aceita token e retorna dados',response.status==200 and 'data' in response.json());check('API não permite escopo não concedido',ctx.request.get(BASE+'/api/v1/invoices',headers=headers).status==403);page.reload();check('Token não reaparece após recarregar',token not in page.locator('body').inner_text());card=page.locator('.card').filter(has=page.get_by_role('heading',name=tokenname,exact=True));card.get_by_role('button',name='Revogar').click();check('Revogação efetiva',ctx.request.get(BASE+'/api/v1/services',headers=headers).status==401);logout()
 login(operator['email'],operator['password']);go('/admin/faturas');check('Operador somente leitura sem botão financeiro',not page.get_by_role('button',name='Confirmar recebimento').count());check('Menu não exibe produtos administrativos',page.locator('a[href$="/admin/produtos"]').count()==0);csrf=page.locator('meta[name=csrf-token]').get_attribute('content');check('Permissão bloqueia POST com CSRF válido',ctx.request.post(BASE+f'/admin/faturas/{invoice}/confirmar',form={'_token':csrf,'note':'Unauthorized test'}).status==403);check('Operador bloqueado nas integrações',page.goto(BASE+'/admin/integracoes').status==403);go('/painel');logout()
 login(F['admin'],F['password']);go(f'/admin/produtos/{a}/editar');check('Cancelamento restitui estoque','5'==page.locator('[name=stock]').input_value());go('/admin/relatorios')
 with page.expect_download()as d:page.get_by_role('link',name='Exportar faturas CSV',exact=True).click()
 file=d.value.path();check('CSV de faturas é baixado',b'total_centavos' in Path(file).read_bytes());go('/admin/conciliacao');page.set_viewport_size({'width':390,'height':900});check('Administração móvel sem overflow',page.evaluate('document.documentElement.scrollWidth <= innerWidth+1'));page.set_viewport_size({'width':1440,'height':1000});logout()
 go('/conhecimento?q='+suffix);page.get_by_role('link',name='Guia '+suffix,exact=True).click();check('Busca abre artigo publicado',page.url.endswith('/conhecimento/'+slug));check('Artigo escapa HTML',page.evaluate('window.kb_xss === undefined') and '<script>window.kb_xss=1</script>' in page.locator('body').inner_text());check('Sem exceções JavaScript',not errors);check('Sem assets ausentes',not assets)
 b.close()
report={'results':results,'js_errors':errors,'failed_assets':assets};(ROOT/'docs/BROWSER-EXPANSION-RESULTS.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));print(f'{sum(x["passed"]for x in results)} passed / {sum(not x["passed"]for x in results)} failed');sys.exit(any(not x['passed']for x in results))
