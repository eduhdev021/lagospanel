const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });
  const ctx = await browser.createBrowserContext();
  const c = await ctx.newPage();
  await c.setViewport({ width: 1440, height: 900 });
  await c.goto('http://localhost:8080/entrar/', { waitUntil: 'networkidle0' });
  await c.type('input[name="log"]', 'cliente@lagos.com');
  await c.type('input[name="pwd"]', 'cliente123');
  await Promise.all([c.click('button[type="submit"]'), c.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await c.goto('http://localhost:8080/painel/faturas/', { waitUntil: 'networkidle0' });
  const href = await c.evaluate(() => document.querySelector('a.btn[href*="/pagamento/"]').href);
  await c.goto(href + '&print=1', { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 1400));
  await c.screenshot({ path: '/home/user/lagospanel/screenshots/68-fatura-pdf.jpg', type: 'jpeg', quality: 85 });
  console.log('ok 68-fatura-pdf');
  await ctx.close();
  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
