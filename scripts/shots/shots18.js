const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });
  const shot = async (p, n, full = false) => { await new Promise((r) => setTimeout(r, 900)); await p.screenshot({ path: '/home/user/lagospanel/screenshots/' + n + '.jpg', type: 'jpeg', quality: 85, fullPage: full }); console.log('ok', n); };

  // admin
  const a = await browser.newPage();
  await a.setViewport({ width: 1440, height: 900 });
  await a.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  await a.type('#user_login', 'eduardo');
  await a.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([a.click('#wp-submit'), a.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await a.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-reports', { waitUntil: 'networkidle0' });
  await shot(a, '67-admin-relatorios', true);
  await a.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-settings', { waitUntil: 'networkidle0' });
  await shot(a, '69-admin-webhooks', true);
  await a.close();

  // cliente: fatura PDF
  const c = await browser.newPage();
  await c.setViewport({ width: 1440, height: 900 });
  await c.goto('http://localhost:8080/entrar/', { waitUntil: 'networkidle0' });
  await c.type('input[name="log"]', 'cliente@lagos.com');
  await c.type('input[name="pwd"]', 'cliente123');
  await Promise.all([c.click('button[type="submit"]'), c.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await c.goto('http://localhost:8080/painel/faturas/', { waitUntil: 'networkidle0' });
  const href = await c.evaluate(() => document.querySelector('a.btn[href*="/pagamento/"]').href);
  await c.goto(href + '&print=1', { waitUntil: 'networkidle0' });
  await shot(c, '68-fatura-pdf');
  await c.close();

  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
