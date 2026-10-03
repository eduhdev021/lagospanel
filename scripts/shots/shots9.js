/**
 * LagosPanel — Screenshots v0.6 (Gateways estilo Paymenter)
 * Fluxo real por cliques: faturas → checkout → gateway → sandbox/manual
 * Contextos limpos (browser contexts separados) + checagem de overflow 360px.
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
  const go = (page, url) => page.goto(url, { waitUntil: 'networkidle0', timeout: 45000 }).catch(() => {});
  const shot = async (page, name, opt = {}) => {
    await new Promise((r) => setTimeout(r, opt.wait || 700));
    await page.screenshot({ path: `${OUT}/${name}.jpg`, type: 'jpeg', quality: 85, fullPage: !!opt.fullPage });
    console.log('ok', name);
  };
  const overflow = async (page, label) => {
    const r = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    console.log(r > 1 ? `OVERFLOW ${label}: +${r}px` : `sem overflow ${label}`);
  };
  const pickGateway = (page, gw) => page.evaluate((g) => {
    const input = document.querySelector(`input[value="${g}"]`);
    input.closest('form').submit();
  }, gw);

  // ── ADMIN: gateways ──
  const ctxA = await browser.createBrowserContext();
  const adm = await ctxA.newPage();
  await adm.setViewport({ width: 1440, height: 900 });
  await go(adm, BASE + '/wp-login.php');
  await new Promise((r) => setTimeout(r, 400));
  await adm.type('#user_login', 'eduardo');
  await adm.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([adm.click('#wp-submit'), adm.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await go(adm, BASE + '/wp-admin/admin.php?page=lagos-gateways');
  await shot(adm, '45-admin-gateways', { fullPage: true, wait: 900 });
  await go(adm, BASE + '/wp-admin/admin.php?page=lagos-gateways&edit=mercadopago');
  await shot(adm, '46-admin-gateway-mp', { fullPage: true, wait: 900 });
  await ctxA.close();

  // ── CLIENTE desktop: fluxo de pagamento ──
  const ctxC = await browser.createBrowserContext();
  const cli = await ctxC.newPage();
  await cli.setViewport({ width: 1440, height: 900 });
  await go(cli, BASE + '/entrar/');
  await cli.type('input[name="log"]', 'cliente@lagos.com');
  await cli.type('input[name="pwd"]', 'cliente123');
  await Promise.all([cli.click('button[type="submit"]'), cli.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await go(cli, BASE + '/painel/faturas/');
  await cli.evaluate(() => { document.querySelector('a.btn[href*="/pagamento/"]').click(); });
  await new Promise((r) => setTimeout(r, 800));
  await shot(cli, '41-checkout-metodos', { fullPage: true });

  // Pix demo (QR)
  await Promise.all([pickGateway(cli, 'pix'), cli.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await shot(cli, '42-checkout-pix', { fullPage: true });
  await go(cli, BASE + '/painel/faturas/');
  await cli.evaluate(() => { document.querySelector('a.btn[href*="/pagamento/"]').click(); });
  await new Promise((r) => setTimeout(r, 800));

  // Mercado Pago sandbox
  await Promise.all([pickGateway(cli, 'mercadopago'), cli.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await shot(cli, '43-checkout-mp-sandbox', { fullPage: true });
  await go(cli, BASE + '/painel/faturas/');
  await cli.evaluate(() => { document.querySelector('a.btn[href*="/pagamento/"]').click(); });
  await new Promise((r) => setTimeout(r, 800));

  // Manual (instruções)
  await Promise.all([pickGateway(cli, 'manual'), cli.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await shot(cli, '44-checkout-manual', { fullPage: true });

  // ── CLIENTE mobile 360px ──
  const ctxM = await browser.createBrowserContext();
  const mob = await ctxM.newPage();
  await mob.setViewport({ width: 360, height: 740, deviceScaleFactor: 2 });
  await go(mob, BASE + '/entrar/');
  await mob.type('input[name="log"]', 'cliente@lagos.com');
  await mob.type('input[name="pwd"]', 'cliente123');
  await Promise.all([mob.click('button[type="submit"]'), mob.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await go(mob, BASE + '/painel/faturas/');
  await mob.evaluate(() => { document.querySelector('a.btn[href*="/pagamento/"]').click(); });
  await new Promise((r) => setTimeout(r, 900));
  await shot(mob, '47-mobile360-checkout', { fullPage: true });
  await overflow(mob, '360 checkout');
  await Promise.all([pickGateway(mob, 'mercadopago'), mob.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await shot(mob, '48-mobile360-mp', { fullPage: true });
  await overflow(mob, '360 mp-sandbox');

  await ctxM.close();
  await ctxC.close();
  await browser.close();
  console.log('DONE');
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
