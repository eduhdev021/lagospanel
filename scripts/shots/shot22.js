const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });
  const ctx = await browser.createBrowserContext();
  const page = await ctx.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  await page.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 400));
  await page.type('#user_login', 'eduardo');
  await page.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([page.click('#wp-submit'), page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  console.log('logado:', page.url());
  await page.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-modules', { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 800));
  await page.screenshot({ path: '/home/user/lagospanel/screenshots/22-admin-modulos.jpg', type: 'jpeg', quality: 85, fullPage: true });
  console.log('ok 22-admin-modulos');
  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
