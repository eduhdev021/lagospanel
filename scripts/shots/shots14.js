const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });
  const shot = async (page, name, full = false) => {
    await new Promise((r) => setTimeout(r, 600));
    await page.screenshot({ path: '/home/user/lagospanel/screenshots/' + name + '.jpg', type: 'jpeg', quality: 85, fullPage: full });
    console.log('ok', name);
  };

  // login mobile + esqueci senha (360px)
  const ctx = await browser.createBrowserContext();
  const m = await ctx.newPage();
  await m.setViewport({ width: 360, height: 740, deviceScaleFactor: 2 });
  await m.goto('http://localhost:8080/entrar/?lagos_forgot=1', { waitUntil: 'networkidle0' });
  let ov = await m.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  console.log('overflow 360 esqueci:', ov);
  await shot(m, '55-login-esqueci');

  // cliente desktop: perfil com gravatar
  const ctxC = await browser.createBrowserContext();
  const c = await ctxC.newPage();
  await c.setViewport({ width: 1440, height: 900 });
  await c.goto('http://localhost:8080/entrar/', { waitUntil: 'networkidle0' });
  await c.type('input[name="log"]', 'cliente@lagos.com');
  await c.type('input[name="pwd"]', 'cliente123');
  await Promise.all([c.click('button[type="submit"]'), c.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await c.goto('http://localhost:8080/painel/perfil/', { waitUntil: 'networkidle0' });
  await shot(c, '54-perfil-gravatar', true);
  await ctxC.close();
  await ctx.close();

  // admin: clientes, auditoria, conexões (10 módulos)
  const ctxA = await browser.createBrowserContext();
  const a = await ctxA.newPage();
  await a.setViewport({ width: 1440, height: 900 });
  await a.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 400));
  await a.type('#user_login', 'eduardo');
  await a.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([a.click('#wp-submit'), a.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await a.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-clients', { waitUntil: 'networkidle0' });
  await shot(a, '56-admin-clientes', true);
  await a.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-audit', { waitUntil: 'networkidle0' });
  await shot(a, '57-admin-auditoria', true);
  await a.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-modules', { waitUntil: 'networkidle0' });
  await shot(a, '58-admin-modulos-whmcs', true);
  await ctxA.close();

  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
