/**
 * LagosPanel — Retake completo v0.3 (roxo + módulos)
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
  const loginCliente = async () => {
    await go(BASE + '/entrar/');
    await page.type('input[name="log"]', 'cliente@lagos.com');
    await page.type('input[name="pwd"]', 'cliente123');
    await Promise.all([
      page.click('button[type="submit"]'),
      page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
    ]);
  };

  await page.setViewport({ width: 1440, height: 900, deviceScaleFactor: 1 });

  // ===== PÚBLICO =====
  await go(BASE + '/');
  await shot('01-home', { fullPage: true });
  await go(BASE + '/loja/');
  await shot('02-loja', { fullPage: true });
  await go(BASE + '/loja/servidor-minecraft-start/');
  await shot('03-produto');
  await go(BASE + '/entrar/');
  await shot('04-login');

  // ===== CLIENTE =====
  await loginCliente();
  await shot('05-painel');

  await go(BASE + '/painel/faturas/');
  await shot('06-faturas');
  const pixHref = await page.$eval('a[href*="lagos_pix"]', (a) => a.href).catch(() => null);
  if (pixHref) { await go(pixHref); await shot('07-pix', { wait: 500 }); }

  // compra Minecraft → pagamento → provisionamento (Pterodactyl simulado)
  await go(BASE + '/loja/servidor-minecraft-start/');
  const buy = await page.$eval('a[href*="lagos_order"]', (a) => a.href).catch(() => null);
  if (buy) { await go(buy); }
  await go(BASE + '/painel/faturas/');
  const pay = await page.$eval('a[href*="lagos_pay"]', (a) => a.href).catch(() => null);
  if (pay) { await go(pay); }
  await go(BASE + '/painel/servicos/');
  await shot('23-servicos-provisionados', { fullPage: true });

  await go(BASE + '/painel/suporte/');
  await shot('08-suporte', { fullPage: true });
  await go(BASE + '/painel/perfil/');
  await shot('09-perfil', { fullPage: true });

  // ===== CARRINHO =====
  await go(BASE + '/loja/');
  let addLinks = await page.$$eval('a[href*="lagos_cart_add"]', (as) => as.map((a) => a.href));
  if (addLinks[0]) await go(addLinks[0]);
  await go(BASE + '/loja/');
  addLinks = await page.$$eval('a[href*="lagos_cart_add"]', (as) => as.map((a) => a.href));
  if (addLinks[4]) await go(addLinks[4]);
  await go(BASE + '/carrinho/');
  await shot('13-carrinho', { fullPage: true });
  await page.type('input[name="coupon"]', 'LAGOS10');
  await Promise.all([
    page.click('input[name="coupon"] ~ button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
  ]);
  await shot('14-carrinho-cupom');
  const rm = await page.$$eval('a[href*="lagos_cart_remove"]', (as) => as.map((a) => a.href));
  for (const r of rm) await go(r);

  // ===== DARK MODE =====
  await go(BASE + '/');
  await page.click('[data-theme-toggle]');
  await shot('15-dark-home');
  await go(BASE + '/painel/');
  await shot('16-dark-painel');
  await go(BASE + '/loja/');
  await shot('17-dark-loja');

  // ===== ADMIN =====
  await go(BASE + '/wp-login.php');
  await new Promise((r) => setTimeout(r, 500));
  await page.type('#user_login', 'eduardo');
  await page.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([
    page.click('#wp-submit'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
  ]);
  await go(BASE + '/wp-admin/admin.php?page=lagospanel');
  await new Promise((r) => setTimeout(r, 900));
  if (await page.$('#user_login')) throw new Error('login admin falhou');
  await shot('10-admin');
  await go(BASE + '/wp-admin/admin.php?page=lagos-modules');
  await shot('22-admin-modulos', { fullPage: true, wait: 900 });
  await go(BASE + '/wp-admin/edit.php?post_type=lagos_product');
  await shot('10b-admin-produtos');

  // ===== MOBILE =====
  await page.setViewport({ width: 390, height: 844, deviceScaleFactor: 2 });
  await go(BASE + '/');
  await shot('11-mobile-home');
  await go(BASE + '/painel/');
  await shot('12-mobile-painel');

  await browser.close();
  console.log('DONE');
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
