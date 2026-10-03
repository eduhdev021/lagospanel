const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  await page.goto('http://localhost:8080/entrar/', { waitUntil: 'networkidle0' });
  await page.type('input[name="log"]', 'cliente@lagos.com');
  await page.type('input[name="pwd"]', 'cliente123');
  await Promise.all([page.click('button[type="submit"]'), page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  console.log('após login:', page.url());
  await page.goto('http://localhost:8080/painel/faturas/', { waitUntil: 'networkidle0' });
  console.log('faturas url:', page.url());
  const links = await page.evaluate(() => Array.from(document.querySelectorAll('a')).map(a => a.getAttribute('href')).filter(h => h && h.includes('pagamento')).slice(0, 5));
  console.log('links pagamento:', JSON.stringify(links, null, 1));
  const title = await page.evaluate(() => document.querySelector('h2,h1')?.textContent);
  console.log('título página:', title);
  await browser.close();
})().catch(e => { console.error('ERRO', e.message); process.exit(1); });
