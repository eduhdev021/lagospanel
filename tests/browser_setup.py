"""Destructive only to a fresh isolated LOCAL installation supplied by explicit environment variables.
Prepare via scripts/prepare-web.sh; never point at a real installation or the regular demo.
"""
import os,json,re,secrets,sqlite3,ipaddress
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).resolve().parents[1]
BASE=os.environ['LAGOS_SETUP_TEST_URL'];INSTALL=Path(os.environ['LAGOS_SETUP_TEST_ROOT']).resolve();KEY=Path(os.environ['LAGOS_SETUP_TEST_KEY']).read_text().strip()
assert os.environ.get('LAGOS_TEST_MODE')=='1' and INSTALL!=ROOT and '/.cache/' in str(INSTALL)
assert urlparse(BASE).scheme=='http' and ipaddress.ip_address(urlparse(BASE).hostname).is_loopback
assert not (INSTALL/'storage/app/private/installed.lock').exists()
(ROOT/'docs/BROWSER-SETUP-RESULTS.json').unlink(missing_ok=True)
results=[];errors=[];password=secrets.token_hex(16)+'Aa1';email='installer-test@example.test'
def check(label,ok):results.append({'test':label,'passed':bool(ok)});print(('PASS 'if ok else'FAIL ')+label)
with sync_playwright()as pw:
 b=pw.chromium.launch(headless=True,args=['--no-sandbox']);ctx=b.new_context(viewport={'width':1280,'height':960});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
 check('Site fechado durante instalação',ctx.request.get(BASE+'/loja').status==503)
 r=p.goto(BASE+'/instalar');check('Assistente exige chave privada',r.status==200 and p.locator('[name=setup_key]').count()==1 and not p.locator('[name=password]').count())
 check('CSRF bloqueia conclusão sem sessão',ctx.request.post(BASE+'/instalar/concluir',form={}).status==419)
 p.locator('[name=setup_key]').fill(KEY);p.get_by_role('button',name='Autorizar instalação').click();check('Chave autoriza etapas sem ir na URL','concluir' in p.locator('body').inner_text().lower() and KEY not in p.url and KEY not in p.content())
 p.set_viewport_size({'width':390,'height':900});check('Instalador sem overflow móvel',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.set_viewport_size({'width':1280,'height':960})
 p.locator('[name=site_name]').fill('Painel instalado pelo navegador');p.locator('[name=url]').fill(BASE);p.locator('[name=db_driver]').select_option('sqlite');p.locator('[name=admin_name]').fill('Administrador inicial');p.locator('[name=admin_email]').fill(email);p.locator('[name=password]').fill(password);p.locator('[name=password_confirmation]').fill(password);p.locator('[name=ack]').check();p.get_by_role('button',name='Concluir instalação',exact=True).click();check('Instalação web concluída','Instalação concluída' in p.locator('body').inner_text());check('Senha não aparece no resultado',password not in p.content())
 check('Instalador bloqueado após concluir',ctx.request.get(BASE+'/instalar').status==404)
 check('Chave consumida e bloqueio persistente',(INSTALL/'storage/app/private/installed.lock').exists() and not (INSTALL/'storage/app/private/setup.json').exists())
 p.get_by_role('link',name='Entrar no painel',exact=True).click();p.locator('[name=email]').fill(email);p.locator('[name=password]').fill(password);p.get_by_role('button',name='Entrar',exact=True).click();p.wait_for_url('**/painel');check('Administrador criado consegue entrar','Painel instalado pelo navegador' in p.locator('body').inner_text())
 r=p.goto(BASE+'/admin/configuracoes/geral');check('Configuração do site disponível após instalar',r.status==200 and p.locator('[name=name]').input_value()=='Painel instalado pelo navegador')
 with sqlite3.connect(INSTALL/'database/database.sqlite')as db:
  check('Migrations versionadas e somente um administrador',db.execute('select count(*) from migrations').fetchone()[0]==len(list((INSTALL/'database/migrations').glob('*.php'))) and db.execute('select count(*) from users where is_admin=1').fetchone()[0]==1 and db.execute('select count(*) from users').fetchone()[0]==1)
  check('Senha persistida como hash, não texto',password not in db.execute('select password from users').fetchone()[0]);check('Sessão final funciona com banco',db.execute('select count(*) from sessions').fetchone()[0]>=1)
 check('Sem erros JavaScript no instalador',not errors);b.close()
report={'results':results,'javascript_errors':errors,'database':'SQLite real, pasta de instalação isolada','production_TLS':'não homologado; HTTP loopback apenas no teste'};(ROOT/'docs/BROWSER-SETUP-RESULTS.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));raise SystemExit(any(not x['passed']for x in results))
