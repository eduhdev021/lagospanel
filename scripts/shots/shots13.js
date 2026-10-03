const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });

  // cliente: checkout em modo produção (6 gateways reais)
  const ctxC = await browser.createBrowserContext();
  const cli = await ctxC.newPage();
  await cli.setViewport({ width: 1440, height: 900 });
  await cli.goto('http://localhost:8080/entrar/', { waitUntil: 'networkidle0' });
  await cli.type('input[name="log"]', 'cliente@lagos.com');
  await cli.type('input[name="pwd"]', 'cliente123');
  await Promise.all([cli.click('button[type="submit"]'), cli.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await cli.goto('http://localhost:8080/painel/faturas/', { waitUntil: 'networkidle0' });
  const ck = await cli.evaluate(() => document.querySelector('a.btn[href*="/pagamento/"]').getAttribute('href'));
  await cli.goto(ck.startsWith('http') ? ck : 'http://localhost:8080' + ck, { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 700));
  await cli.screenshot({ path: '/home/user/lagospanel/screenshots/41-checkout-metodos.jpg', type: 'jpeg', quality: 85, fullPage: true });
  console.log('ok 41 (checkout 6 gateways)');
  await ctxC.close();

  // admin: lista com 9 gateways
  const ctxA = await browser.createBrowserContext();
  const adm = await ctxA.newPage();
  await adm.setViewport({ width: 1440, height: 900 });
  await adm.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 400));
  await adm.type('#user_login', 'eduardo');
  await adm.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([adm.click('#wp-submit'), adm.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await adm.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-gateways', { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 800));
  await adm.screenshot({ path: '/home/user/lagospanel/screenshots/45-admin-gateways.jpg', type: 'jpeg', quality: 85, fullPage: true });
  console.log('ok 45 (admin 9 gateways)');
  await ctxA.close();

  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
