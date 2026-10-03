const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });
  const shot = async (p, n, full = false) => { await new Promise((r) => setTimeout(r, 700)); await p.screenshot({ path: '/home/user/lagospanel/screenshots/' + n + '.jpg', type: 'jpeg', quality: 85, fullPage: full }); console.log('ok', n); };

  // cliente com moeda USD
  const ctx = await browser.createBrowserContext();
  const c = await ctx.newPage();
  await c.setViewport({ width: 1440, height: 900 });
  await c.goto('http://localhost:8080/entrar/', { waitUntil: 'networkidle0' });
  await c.type('input[name="log"]', 'cliente@lagos.com');
  await c.type('input[name="pwd"]', 'cliente123');
  await Promise.all([c.click('button[type="submit"]'), c.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await c.setCookie({ name: 'lagos_currency', value: 'USD', url: 'http://localhost:8080' });
  await c.goto('http://localhost:8080/loja/', { waitUntil: 'networkidle0' });
  await shot(c, '64-loja-usd', true);
  // checkout → stripe (USD)
  await c.goto('http://localhost:8080/painel/faturas/', { waitUntil: 'networkidle0' });
  await c.evaluate(() => { document.querySelector('a.btn[href*="/pagamento/"]').click(); });
  await new Promise((r) => setTimeout(r, 800));
  await c.evaluate(() => { const i = document.querySelector('input[value="stripe"]'); i.closest('form').submit(); });
  await new Promise((r) => setTimeout(r, 1200));
  await shot(c, '65-checkout-usd', true);
  await ctx.close();

  // wp-login branco-labeled
  const ctx2 = await browser.createBrowserContext();
  const l = await ctx2.newPage();
  await l.setViewport({ width: 1440, height: 900 });
  await l.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  await shot(l, '66-login-lagos');
  await ctx2.close();

  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
