/**
 * LagosPanel — Screenshots v0.5 (domínios, downloads, status, afiliados, admin chart, mobile 360)
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
  const shot = async (name, opt = {}) => {
    await new Promise((r) => setTimeout(r, opt.wait || 700));
    await page.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality: 85, fullPage: !!opt.fullPage });
    console.log('ok', name);
  };

  await page.setViewport({ width: 1440, height: 900 });

  await go(BASE + '/verificar-dominio/?domain=lagossolucoes');
  await shot('31-dominios', { fullPage: true, wait: 3000 });
  await go(BASE + '/downloads/');
  await shot('32-downloads');
  await go(BASE + '/status/');
  await shot('33-status', { fullPage: true });

  await go(BASE + '/entrar/');
  await page.type('input[name="log"]', 'cliente@lagos.com');
  await page.type('input[name="pwd"]', 'cliente123');
  await Promise.all([page.click('button[type="submit"]'), page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await go(BASE + '/painel/afiliados/');
  await shot('34-afiliados', { fullPage: true });
  await go(BASE + '/painel/suporte/');
  await shot('35-suporte-avaliacao', { fullPage: true });

  await go(BASE + '/wp-login.php');
  await new Promise((r) => setTimeout(r, 500));
  await page.type('#user_login', 'eduardo');
  await page.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([page.click('#wp-submit'), page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await go(BASE + '/wp-admin/admin.php?page=lagospanel');
  await shot('36-admin-grafico', { fullPage: true, wait: 1400 });

  await page.setViewport({ width: 360, height: 740, deviceScaleFactor: 2 });
  await go(BASE + '/');
  await shot('37-mobile360-home');
  await go(BASE + '/verificar-dominio/?domain=meusite');
  await shot('38-mobile360-dominios', { wait: 3000 });
  await go(BASE + '/painel/');
  await shot('39-mobile360-painel');
  await go(BASE + '/painel/faturas/');
  await shot('40-mobile360-faturas-cards');

  await browser.close();
  console.log('DONE');
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
