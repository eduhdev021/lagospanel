"""Local demo only. No external API calls, no production changes."""
import json, os, ipaddress
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8080')
assert urlparse(BASE).scheme=='http' and ipaddress.ip_address(urlparse(BASE).hostname).is_loopback
results=[]; errors=[]
def check(name,value):
 results.append({'test':name,'passed':bool(value)}); print(('PASS ' if value else 'FAIL ')+name)
with sync_playwright() as pw:
 browser=pw.chromium.launch(args=['--no-sandbox'])
 context=browser.new_context(viewport={'width':1366,'height':950})
 p=context.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 p.goto(BASE+'/entrar');p.locator('[name=email]').fill('admin@experience.invalid');p.locator('[name=password]').fill('DemoOnly-Experience-4928');p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel')
 p.goto(BASE+'/admin/configuracoes')
 urls=p.locator('[data-settings-item] a').evaluate_all('(nodes)=>nodes.map(n=>n.href)')
 if not urls: urls=p.locator('a[data-settings-item]').evaluate_all('(nodes)=>nodes.map(n=>n.href)')
 check('Settings navigation has links',len(urls)>=20)
 for url in dict.fromkeys(urls):
  if urlparse(url).netloc!=urlparse(BASE).netloc:continue
  r=p.goto(url);check('GET '+urlparse(url).path,r.status==200)
 p.goto(BASE+'/admin/configuracoes/operacao/billing')
 for width in [320,390,768,1366]:
  p.set_viewport_size({'width':width,'height':950});check('Billing responsive '+str(width),p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 p.set_viewport_size({'width':1366,'height':950})
 p.locator('[name="values[issuer_name]"]').fill('Empresa preservada no formulário')
 p.get_by_role('button',name='Salvar configurações',exact=True).click()
 p.locator('.form-validation-summary').wait_for()
 check('Save explains missing password and consent', 'senha' in p.locator('.form-validation-summary').inner_text().lower() and 'Revisei' in p.locator('.form-validation-summary').inner_text())
 check('Invalid browser form keeps entered company',p.locator('[name="values[issuer_name]"]').input_value()=='Empresa preservada no formulário')
 p.locator('[name=password]').fill('wrong-password');p.locator('[name=ack]').check();p.get_by_role('button',name='Salvar configurações',exact=True).click();p.wait_for_load_state()
 check('Wrong confirmation keeps non-secret draft',p.locator('[name="values[issuer_name]"]').input_value()=='Empresa preservada no formulário')
 check('Wrong confirmation does not re-display password',p.locator('[name=password]').input_value()=='')
 p.locator('[name=password]').fill('DemoOnly-Experience-4928');p.locator('[name=ack]').check();p.get_by_role('button',name='Salvar configurações',exact=True).click();p.wait_for_load_state()
 check('Billing can save by clicking',p.locator('.alert-success').count()==1)
 p.reload();check('Saved company persists after reload',p.locator('[name="values[issuer_name]"]').input_value()=='Empresa preservada no formulário')
 p.goto(BASE+'/admin/configuracoes/login-social')
 form=p.locator('form[action$="/github"]');form.locator('[name=client_id]').fill('new-public-client-id');form.locator('[name=password]').fill('wrong-password');form.get_by_role('button',name='Salvar GitHub').click();p.wait_for_load_state()
 check('Social error keeps the submitted Client ID',p.locator('form[action$="/github"] [name=client_id]').input_value()=='new-public-client-id')
 check('Social draft not copied to other providers',p.locator('form[action$="/google"] [name=client_id]').input_value()=='demo-client')
 form=p.locator('form[action$="/github"]');form.locator('[name=password]').fill('DemoOnly-Experience-4928');form.get_by_role('button',name='Salvar GitHub').click();p.wait_for_load_state()
 check('Social can save with blank existing secret',p.locator('.alert-success').count()==1)
 check('Saved Client ID remains visible',p.locator('form[action$="/github"] [name=client_id]').input_value()=='new-public-client-id')
 check('Saved secret never in HTML','demo-secret-not-real' not in p.content())
 p.goto(BASE+'/admin/configuracoes/email');p.locator('[name=mailer]').select_option('log');p.locator('[name=smtp_host]').fill('smtp.fixture.invalid');p.locator('[name=smtp_username]').fill('public-user');p.locator('[name=mail_from_address]').fill('sender@fixture.invalid');p.locator('[name=smtp_password]').fill('secret-fixture-not-real');p.get_by_role('button',name='Salvar configuração de e-mail').click();p.wait_for_load_state()
 check('Email can save',p.locator('.alert-success').count()==1)
 check('Email keeps host username and sender',p.locator('[name=smtp_host]').input_value()=='smtp.fixture.invalid' and p.locator('[name=smtp_username]').input_value()=='public-user' and p.locator('[name=mail_from_address]').input_value()=='sender@fixture.invalid')
 check('Email hides only password',p.locator('[name=smtp_password]').input_value()=='' and 'secret-fixture-not-real' not in p.content())
 p.goto(BASE+'/admin/configuracoes/geral');p.locator('[name=name]').fill('');p.locator('[name=registration_enabled][type=checkbox]').uncheck()
 # Exercise server-side validation without bypassing auth or CSRF.
 p.locator('form:has([name=name])').evaluate('(f)=>f.noValidate=true');p.get_by_role('button',name='Salvar identidade do site').click();p.wait_for_load_state()
 check('Unchecked registration stays unchecked after validation',not p.locator('[name=registration_enabled][type=checkbox]').is_checked())
 check('Panel script URL is same-origin versioned',p.locator('script[src*="admin-forms.js?v="]').count()==1)
 check('No JavaScript errors',not errors)
 browser.close()
(ROOT/'docs/BROWSER-SETTINGS-FORMS-RESULTS.json').write_text(json.dumps({'results':results,'javascript_errors':errors,'scope':'Local fixture. Real browser form submissions, no external calls.'},indent=2,ensure_ascii=False))
raise SystemExit(any(not r['passed'] for r in results))
