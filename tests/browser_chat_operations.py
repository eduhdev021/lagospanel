"""LOCAL browser + mocked Ollama worker. Never uses production credentials."""
import json,os,subprocess,ipaddress
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[1];BASE=os.environ.get('LAGOS_TEST_URL','http://127.0.0.1:8080')
assert urlparse(BASE).scheme=='http' and ipaddress.ip_address(urlparse(BASE).hostname).is_loopback
ENV=dict(os.environ,LAGOS_TEST_MODE='1',APP_ENV='local',APP_URL=BASE,DB_CONNECTION='sqlite',DB_DATABASE=str(ROOT/'.cache/experience.sqlite'),MAIL_MAILER='log',SESSION_DRIVER='file',CACHE_STORE='file')
def fixture(*args):return subprocess.check_output(['php','tests/chat-fixture.php',*map(str,args)],cwd=ROOT,env=ENV,text=True).strip()
fixture('prepare');results=[];errors=[]
def check(label,ok):results.append({'test':label,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+label)
with sync_playwright()as pw:
 b=pw.chromium.launch(args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1440,'height':950});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 def login(name):
  p.goto(BASE+'/entrar');p.locator('[name=email]').fill(name+'@experience.invalid');p.locator('[name=password]').fill('DemoOnly-Experience-4928');p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel')
 login('client');check('Chat returns 200',p.goto(BASE+'/painel/chat').status==200);p.screenshot(path=str(ROOT/'docs/screenshots/chat-welcome-desktop.png'))
 p.locator('[data-chat-suggestion]').first.click();check('Suggestion fills composer',len(p.locator('[name=body]').input_value())>10);p.locator('[name=consent]').check();p.evaluate('window.chat_navigation_marker=123');p.locator('[name=body]').press('Enter');expect(p.locator('[data-ai-turn]')).to_have_count(1);check('First message does not reload page',p.evaluate('window.chat_navigation_marker===123'));check('Composer disables sending while queued',p.locator('[data-chat-send]').is_disabled());check('Consent remembered per conversation',p.locator('[name=consent]').count()==0)
 tid=int(p.locator('[data-ai-turn]').first.get_attribute('data-ai-turn'));check('Real worker completes with mocked Ollama',fixture('answer',tid)=='done');expect(p.locator('[data-state=done]')).to_have_count(1,timeout=15000);check('Markdown bold and code rendered',p.locator('[data-ai-answer] strong').count()>0 and p.locator('[data-ai-answer] pre code').count()>0);check('Model HTML remains inert',not p.evaluate('Boolean(window.chat_xss)'));check('Send reenabled after answer',p.locator('[data-chat-send]').is_enabled());
 p.screenshot(path=str(ROOT/'docs/screenshots/chat-conversation-desktop.png'))
 p.locator('[name=body]').fill('Explique melhor.');p.locator('[name=body]').press('Shift+Enter');check('Shift Enter inserts newline without sending',p.locator('[data-ai-turn]').count()==1 and '\n' in p.locator('[name=body]').input_value());p.locator('[data-chat-send]').click();expect(p.locator('[data-ai-turn]')).to_have_count(2);tid2=int(p.locator('[data-ai-turn]').last.get_attribute('data-ai-turn'));fixture('fail',tid2);expect(p.locator('[data-state=failed]')).to_have_count(1,timeout=15000);p.locator('[data-chat-retry]:visible').click();check('Failure retry fills draft, never auto resends',p.locator('[data-ai-turn]').count()==2 and p.locator('[name=body]').input_value().strip()=='Explique melhor.');
 p.locator('[data-chat-tools]>summary').click();p.locator('[name=title]').fill('Dúvidas sobre meu domínio');p.get_by_role('button',name='Renomear',exact=True).click();expect(p.locator('.chat-history-item.is-active strong')).to_have_text('Dúvidas sobre meu domínio');check('Rename updates sidebar without navigation',p.evaluate('window.chat_navigation_marker===123'))
 for width in [320,390,768,1440]:
  p.set_viewport_size({'width':width,'height':900});check('Chat responsive '+str(width),p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
 p.set_viewport_size({'width':390,'height':900});p.locator('[name=body]').fill('');p.locator('[name=body]').dispatch_event('input');p.screenshot(path=str(ROOT/'docs/screenshots/chat-conversation-mobile.png'),animations='disabled')
 p.locator('.chat-history-mobile').click();check('Mobile history opens',p.locator('.chat-history').is_visible());p.locator('.chat-history-close').click();check('Mobile history closes',not p.locator('.chat-history').is_visible());
 p.reload();check('Conversation persists after reload',p.locator('[data-ai-turn]').count()==2 and p.locator('[name=consent]').count()==0)
 p.set_viewport_size({'width':1440,'height':950});p.locator('form[action$="/sair"] button').click();login('admin');p.goto(BASE+'/admin/configuracoes');check('Operational settings added to hub',p.locator('[data-settings-item]').count()==22)
 p.locator('#settings-search').fill('faturamento');check('Billing discoverable by search',p.locator('[data-settings-item]:visible').count()==1)
 for section in ['billing','payments','automation','support','resources']:
  check('Operational page '+section,p.goto(BASE+'/admin/configuracoes/operacao/'+section).status==200)
 p.goto(BASE+'/admin/configuracoes/operacao/billing');p.locator('[name="values[issuer_name]"]').fill('Emissor de teste do navegador');p.locator('[name=password]').fill('DemoOnly-Experience-4928');p.locator('[name=ack]').check();p.get_by_role('button',name='Salvar configurações',exact=True).click();check('Billing configuration persists',p.locator('[name="values[issuer_name]"]').input_value()=='Emissor de teste do navegador' and 'Configuração salva' in p.locator('body').inner_text())
 p.goto(BASE+'/admin/configuracoes/operacao/automation');p.screenshot(path=str(ROOT/'docs/screenshots/automation-settings-desktop.png'),full_page=True)
 p.set_viewport_size({'width':390,'height':900});check('Operational settings responsive',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));check('Coverage page discloses missing features',p.goto(BASE+'/admin/configuracoes/recursos-disponiveis').status==200 and 'Sem motor tributário' in p.locator('body').inner_text());check('No JavaScript errors',not errors);b.close()
(ROOT/'docs/BROWSER-CHAT-OPERATIONS-RESULTS.json').write_text(json.dumps({'results':results,'javascript_errors':errors,'network':'Ollama mocked locally; no real provider credentials'},indent=2,ensure_ascii=False))
raise SystemExit(any(not r['passed']for r in results))
