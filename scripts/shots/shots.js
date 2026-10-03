/**
 * LagosPanel — Gerador de screenshots
 * Uso: node shots.js
 */
const puppeteer = require('puppeteer-core');

const BASE = 'http://localhost:8080';
const OUT = '/home/user/lagospanel/screenshots';

(async () => {
  const browser = await puppeteer.launch({
    executablePath: '/usr/bin/chromium',
    headless: 'new',
    args: [
      '--no-sandbox',
      '--disable-gpu',
      '--disable-dev-shm-usage',
      '--force-device-scale-factor=1',
      '--font-render-hinting=none',
      '--hide-scrollbars',
    ],
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1 });

  const go = async (url) => {
    await page.goto(url, { waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {});
  };
  const shot = async (name, { fullPage = false, wait = 700 } = {}) => {
    await new Promise((r) => setTimeout(r, wait));
    await page.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality: 85, fullPage });
    console.log('✓', name);
  };

  // ===== PÁGINAS PÚBLICAS =====
  await go(BASE + '/');
  await shot('01-home', { fullPage: true });

  await go(BASE + '/loja/');
  await shot('02-loja', { fullPage: true });

  await go(BASE + '/loja/lagos-start/');
  await shot('03-produto');

  await go(BASE + '/entrar/');
  await shot('04-login');

  // ===== LOGIN CLIENTE =====
  await page.type('input[name="log"]', 'cliente@lagos.com');
  await page.type('input[name="pwd"]', 'cliente123');
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
  ]);
  await shot('05-painel');

  // ===== FATURAS + PIX =====
  await go(BASE + '/painel/faturas/');
  await shot('06-faturas');

  const pixHref = await page
    .$eval('a[href*="lagos_pix"]', (a) => a.href)
    .catch(() => null);
  if (pixHref) {
    await go(pixHref);
    await shot('07-pix', { wait: 500 });
  } else {
    console.log('! sem fatura aberta para o modal Pix');
  }

  // ===== SUPORTE / PERFIL =====
  await go(BASE + '/painel/suporte/');
  await shot('08-suporte', { fullPage: true });

  await go(BASE + '/painel/perfil/');
  await shot('09-perfil', { fullPage: true });

  // ===== ADMIN =====
  await go(BASE + '/wp-login.php');
  await page.$eval('#loginform', (f) => f.submit()).catch(async () => {
    await page.type('#user_login', 'eduardo');
    await page.type('#user_pass', 'LagosPanel#2026');
    await Promise.all([
      page.click('#wp-submit'),
      page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
    ]);
  });
  // se caiu no form sem digitar (logout prévio falho), digita agora
  if (await page.$('#user_login')) {
    await page.type('#user_login', 'eduardo');
    await page.type('#user_pass', 'LagosPanel#2026');
    await Promise.all([
      page.click('#wp-submit'),
      page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
    ]);
  }
  await go(BASE + '/wp-admin/admin.php?page=lagospanel');
  await shot('10-admin');

  // ===== MOBILE =====
  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await go(BASE + '/');
  await shot('11-mobile-home');

  await go(BASE + '/painel/');
  await shot('12-mobile-painel');

  await browser.close();
  console.log('DONE');
})().catch((e) => {
  console.error('ERRO:', e.message);
  process.exit(1);
});
