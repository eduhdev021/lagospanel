const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });
  const ctx = await browser.createBrowserContext();
  const a = await ctx.newPage();
  await a.setViewport({ width: 1440, height: 900 });
  await a.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 400));
  await a.type('#user_login', 'eduardo');
  await a.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([a.click('#wp-submit'), a.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  const shot = async (name, full = false) => {
    await new Promise((r) => setTimeout(r, 800));
    await a.screenshot({ path: '/home/user/lagospanel/screenshots/' + name + '.jpg', type: 'jpeg', quality: 85, fullPage: full });
    console.log('ok', name);
  };
  await a.goto('http://localhost:8080/wp-admin/admin.php?page=lagospanel', { waitUntil: 'networkidle0' });
  await shot('59-admin-whmcs-dashboard', true);
  // sino de notificações aberto
  await a.click('#lagos-bell').catch(() => {});
  await new Promise((r) => setTimeout(r, 400));
  await shot('60-admin-whmcs-notificacoes');
  await a.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-clients', { waitUntil: 'networkidle0' });
  await shot('61-admin-whmcs-clientes', true);
  await a.goto('http://localhost:8080/wp-admin/edit.php?post_type=lagos_invoice', { waitUntil: 'networkidle0' });
  await shot('62-admin-whmcs-faturas', true);
  await ctx.close();
  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
