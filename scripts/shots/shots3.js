/**
 * LagosPanel — Screenshots v0.2 (carrinho, cupom, dark mode)
 */
const puppeteer = require('puppeteer-core');
const BASE = 'http://localhost:8080';
const OUT = '/home/user/lagospanel/screenshots';

(async () => {
  const browser = await puppeteer.launch({
    executablePath: '/usr/bin/chromium',
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', '--font-render-hinting=none'],
  });
  const page = await browser.newPage();
  const go = (url) => page.goto(url, { waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {});
  const shot = async (name, { fullPage = false, wait = 700 } = {}) => {
    await new Promise((r) => setTimeout(r, wait));
    await page.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality: 85, fullPage });
    console.log('✓', name);
  };

  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1 });

  // login
  await go(BASE + '/entrar/');
  await page.type('input[name="log"]', 'cliente@lagos.com');
  await page.type('input[name="pwd"]', 'cliente123');
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
  ]);

  // add 2 produtos ao carrinho
  const addLinks = await page.$$eval('a[href*="lagos_cart_add"]', (as) => as.slice(0, 2).map((a) => a.href));
  const loja = await page.evaluate(() => document.querySelector('a[href*="/loja/"]').href);
  await go(loja);
  const links = await page.$$eval('a[href*="lagos_cart_add"]', (as) => as.map((a) => a.href));
  if (links[0]) await go(links[0]);
  await go(loja);
  const links2 = await page.$$eval('a[href*="lagos_cart_add"]', (as) => as.map((a) => a.href));
  if (links2[3]) await go(links2[3]);

  // carrinho com itens
  await go(BASE + '/carrinho/');
  await shot('13-carrinho', { fullPage: true });

  // aplica cupom
  await page.type('input[name="coupon"]', 'LAGOS10');
  await Promise.all([
    page.click('input[name="coupon"] ~ button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
  ]);
  await shot('14-carrinho-cupom');

  // esvazia carrinho (remove itens) para o demo ficar limpo
  const removeLinks = await page.$$eval('a[href*="lagos_cart_remove"]', (as) => as.map((a) => a.href));
  for (const r of removeLinks) await go(r);

  // ===== DARK MODE =====
  await go(BASE + '/');
  await page.click('[data-theme-toggle]');
  await shot('15-dark-home');

  await go(BASE + '/painel/');
  await shot('16-dark-painel');

  await go(BASE + '/loja/');
  await shot('17-dark-loja');

  await browser.close();
  console.log('DONE');
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
