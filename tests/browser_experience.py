"""Local-only UI regression; fixture credentials, never real provider authorization."""
import json,os,ipaddress
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8080')
assert urlparse(BASE).scheme=='http' and ipaddress.ip_address(urlparse(BASE).hostname).is_loopback
results=[];errors=[]
def check(name,ok):
 results.append({'test':name,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+name)
with sync_playwright()as pw:
 browser=pw.chromium.launch(args=['--no-sandbox']);context=browser.new_context(viewport={'width':1440,'height':1000});p=context.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 check('Homepage returns 200',p.goto(BASE+'/').status==200)
 check('Homepage shows real catalog fixture',p.get_by_role('heading',name='Hospedagem Essencial').count()==1)
 (ROOT/'docs/screenshots').mkdir(exist_ok=True)
 p.screenshot(path=str(ROOT/'docs/screenshots/home-desktop.png'),full_page=True)
 for width in [320,390,768,1440]:
  p.set_viewport_size({'width':width,'height':900});check('Homepage responsive '+str(width),p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 p.set_viewport_size({'width':390,'height':900});p.screenshot(path=str(ROOT/'docs/screenshots/home-mobile.png'),full_page=True)
 p.goto(BASE+'/entrar');check('All five social buttons render',p.locator('.social-button').count()==5);check('Login responsive',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 p.locator('[name=email]').fill('admin@experience.invalid');p.locator('[name=password]').fill('DemoOnly-Experience-4928');p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel')
 p.set_viewport_size({'width':1440,'height':1000});check('Settings returns 200',p.goto(BASE+'/admin/configuracoes').status==200)
 check('Settings icons present',p.locator('[data-settings-item] .settings-tile-icon svg').count()==22)
 p.screenshot(path=str(ROOT/'docs/screenshots/settings-desktop.png'),full_page=True)
 p.locator('#settings-search').fill('EMAIL');check('Search ignores accents and matches email',p.locator('[data-settings-item]:visible').count()==1)
 p.locator('#settings-search').fill('no-match-fixture');check('Empty search has recovery button',p.locator('[data-settings-empty]').is_visible());p.locator('[data-settings-reset]').click()
 p.locator('[data-settings-filter=integrations]').click();check('Category filtering',p.locator('[data-settings-item]:visible').count()==3)
 p.locator('[data-settings-filter=all]').click()
 for width in [320,390,768,1440]:
  p.set_viewport_size({'width':width,'height':900});check('Settings responsive '+str(width),p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 p.set_viewport_size({'width':390,'height':900});p.screenshot(path=str(ROOT/'docs/screenshots/settings-mobile.png'),full_page=True)
 p.goto(BASE+'/admin/configuracoes/login-social');check('Five provider forms',p.locator('.provider-setting').count()==5);check('No credential reflected','demo-secret-not-real'not in p.content());check('Social settings mobile layout',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 p.set_viewport_size({'width':1440,'height':1000});p.locator('form[action$="/sair"] button').click();p.goto(BASE+'/entrar');p.locator('[name=email]').fill('client@experience.invalid');p.locator('[name=password]').fill('DemoOnly-Experience-4928');p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel');p.goto(BASE+'/painel/perfil');check('Five account linking forms',p.locator('.connected-provider').count()==5);check('Customer cannot access admin settings',context.request.get(BASE+'/admin/configuracoes').status==403)
 check('No JavaScript errors',not errors);browser.close()
(ROOT/'docs/BROWSER-EXPERIENCE-RESULTS.json').write_text(json.dumps({'results':results,'javascript_errors':errors,'scope':'Local fixture; no live OAuth provider authorization'},indent=2,ensure_ascii=False))
raise SystemExit(any(not r['passed']for r in results))
