"""Real Chromium test, isolated local demo only. Requires `php artisan lagos:demo` and a running server."""
import json,os,re,sys,subprocess,time,uuid
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8080')
F=json.loads((ROOT/'.cache/demo.json').read_text())
results=[];errors=[];badassets=[]
def check(label,ok):
 results.append({'test':label,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+label)
def worker():
 r=subprocess.run(['php','artisan','queue:work','--stop-when-empty','--tries=3','--max-time=30'],cwd=ROOT,capture_output=True,text=True,timeout=45)
 if r.returncode: raise RuntimeError(r.stdout+r.stderr)
with sync_playwright() as p:
 b=p.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':1000});page=ctx.new_page()
 page.on('pageerror',lambda e:errors.append(str(e)))
 page.on('response',lambda r:badassets.append(r.url) if r.status>=400 and r.request.resource_type in ('script','stylesheet','image') else None)
 def go(path):
  r=page.goto(BASE+path);check('HTTP '+path,r.status==200);return r
 def login(email):
  go('/entrar');page.locator('input[name=email]').fill(email);page.locator('input[name=password]').fill(F['password']);page.get_by_role('button',name='Entrar',exact=True).click();page.wait_for_url('**/painel');check('Login '+email,'Visão geral' in page.title())
 go('/loja');check('Catálogo renderiza produtos',page.locator('.catalog-card').count()>=3)
 check('POST sem CSRF é bloqueado',ctx.request.post(BASE+'/entrar',data={'email':F['client'],'password':F['password']}).status==419)
 login(F['client']);go('/loja');page.locator('.catalog-card').first.get_by_role('button',name='Contratar',exact=True).click();page.wait_for_url('**/painel/faturas/*');invoice=int(page.url.rsplit('/',1)[-1]);check('Pedido abre fatura','Fatura #'+str(invoice) in page.locator('body').inner_text());check('Preço calculado no servidor','R$ 24,90' in page.locator('body').inner_text())
 page.get_by_role('button',name=re.compile('Usar saldo')).click();check('Saldo insuficiente não paga','Saldo insuficiente' in page.locator('body').inner_text())
 check('Cliente não acessa admin',page.goto(BASE+'/admin').status==403)
 go('/painel/suporte');page.locator('input[name=subject]').fill('Teste de suporte '+str(uuid.uuid4())[:8]);page.locator('textarea[name=body]').first.fill('<img src=x onerror="window.xss=1"> Ajuda com o serviço.');page.get_by_role('button',name='Enviar ticket').click();check('Ticket criado','Ticket criado' in page.locator('body').inner_text());check('XSS não executa',page.evaluate('window.xss === undefined'));check('XSS aparece como texto','<img src=x' in page.locator('body').inner_text())
 go('/painel');page.screenshot(path=str(ROOT/'.cache/painel-desktop.png'),full_page=True)
 for width in [390,768]:
  page.set_viewport_size({'width':width,'height':900});go('/painel');check(f'Sem overflow horizontal {width}',page.evaluate('document.documentElement.scrollWidth <= innerWidth+1'))
  if page.locator('#lagosMenuToggle').is_visible():
   page.locator('#lagosMenuToggle').click();check(f'Menu móvel abre {width}',page.locator('body').evaluate('(e)=>e.classList.contains("sidebar-open")'));page.keyboard.press('Escape');check(f'Esc fecha menu {width}',not page.locator('body').evaluate('(e)=>e.classList.contains("sidebar-open")'))
 page.screenshot(path=str(ROOT/'.cache/painel-mobile.png'),full_page=True)
 page.set_viewport_size({'width':1440,'height':1000});page.locator('[data-theme-toggle]').click();check('Tema escuro',page.locator('body').evaluate('(e)=>e.classList.contains("theme-dark")'));page.reload();check('Tema persiste',page.locator('body').evaluate('(e)=>e.classList.contains("theme-dark")'));page.locator('[data-theme-toggle]').click()
 page.locator('form[action$="/sair"] button').click();login(F['admin'])
 for path in ['/admin','/admin/produtos','/admin/faturas','/admin/servicos','/admin/clientes','/admin/suporte','/admin/cupons','/admin/integracoes','/admin/operacoes','/admin/auditoria']:go(path)
 go('/admin/faturas');form=page.locator(f'form[action$="/faturas/{invoice}/confirmar"]');form.locator('[name=note]').fill('Conferência de teste local — sem transferência real');form.locator('button').click();check('Admin registra recebimento','Recebimento manual registrado' in page.locator('body').inner_text())
 go('/admin/servicos');form=page.locator('form[action*="/admin/servicos/"]').first;form.locator('[name=status]').select_option('active');form.locator('[name=note]').fill('Ativação de demonstração local');form.locator('button').click();check('Admin ativa serviço manual','Ativo' in page.locator('body').inner_text())
 go('/admin/produtos');page.locator('[name=name]').fill('Produto Browser');page.locator('[name=slug]').fill('browser-'+str(uuid.uuid4())[:8]);page.locator('[name=price]').fill('19,90');page.locator('[name=description]').fill('Produto criado pelo teste de interface.');page.get_by_role('button',name='Salvar produto').click();check('Admin cria produto','Produto salvo' in page.locator('body').inner_text())
 page.locator('form[action$="/sair"] button').click()
 # Real registration, queued e-mail signed link, then reset and replay rejection.
 go('/registrar');email='browser-'+str(uuid.uuid4())[:8]+'@example.test';password='RegistrationPassword123!';page.locator('[name=name]').fill('Cliente Browser');page.locator('[name=email]').fill(email);page.locator('[name=password]').fill(password);page.locator('[name=password_confirmation]').fill(password);page.locator('[name=terms]').check();page.get_by_role('button',name='Criar conta',exact=True).click();page.wait_for_url('**/verificar-email');check('Cadastro exige confirmação','Confirme seu e-mail' in page.title());worker()
 mail=(ROOT/'storage/logs/mail.log').read_text();links=re.findall(r'https?://[^\s<>\[\]()]+/email/verificar/[^\s<>\[\]()]+',mail);link=links[-1].replace('&amp;','&');page.goto(link);check('Link confirma conta',page.url.endswith('/painel'))
 check('Outra conta não lê fatura',page.goto(BASE+f'/painel/faturas/{invoice}').status==404)
 go('/painel');page.locator('form[action$="/sair"] button').click();go('/esqueci-senha');page.locator('[name=email]').fill(email);page.get_by_role('button',name='Enviar link').click();check('Recuperação retorna mensagem genérica','Se a conta existir' in page.locator('body').inner_text());worker();mail=(ROOT/'storage/logs/mail.log').read_text();links=re.findall(r'https?://[^\s<>\[\]()]+/redefinir-senha/[^\s<>\[\]()]+',mail);link=links[-1].replace('&amp;','&');page.goto(link);page.locator('[name=password]').fill('ChangedPassword123!');page.locator('[name=password_confirmation]').fill('ChangedPassword123!');page.get_by_role('button',name='Salvar nova senha').click();check('Redefinição funciona',page.url.endswith('/entrar') and 'Senha alterada' in page.locator('body').inner_text());page.goto(link);page.locator('[name=password]').fill('AnotherPassword123!');page.locator('[name=password_confirmation]').fill('AnotherPassword123!');page.get_by_role('button',name='Salvar nova senha').click();check('Token de redefinição não reutilizável','Link inválido ou expirado' in page.locator('body').inner_text())
 check('Nenhuma exceção JavaScript',not errors);check('Nenhum recurso visual ausente',not badassets)
 b.close()
(ROOT/'docs/BROWSER-RESULTS.json').write_text(json.dumps({'results':results,'js_errors':errors,'failed_assets':badassets},indent=2,ensure_ascii=False))
print(f'{sum(r["passed"] for r in results)} passed / {sum(not r["passed"] for r in results)} failed')
sys.exit(any(not r['passed'] for r in results))
