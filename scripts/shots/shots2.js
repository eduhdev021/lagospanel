/**
 * LagosPanel — Screenshots parte 2 (admin + mobile)
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

  // login admin direto
  await go(BASE + '/wp-login.php');
  await new Promise((r) => setTimeout(r, 600));
  await page.type('#user_login', 'eduardo');
  await page.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([
    page.click('#wp-submit'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
  ]);
  await go(BASE + '/wp-admin/admin.php?page=lagospanel');
  await new Promise((r) => setTimeout(r, 1000));
  if (await page.$('#user_login')) throw new Error('login admin falhou');
  await shot('10-admin');

  // produtos no admin
  await go(BASE + '/wp-admin/edit.php?post_type=lagos_product');
  await shot('10b-admin-produtos');

  // mobile
  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await go(BASE + '/');
  await shot('11-mobile-home');

  await go(BASE + '/painel/');
  await shot('12-mobile-painel');

  await browser.close();
  console.log('DONE');
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
