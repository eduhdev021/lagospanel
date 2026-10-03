/**
 * LagosPanel — Retakes v0.5:
 *  - Admin (contexto limpo, sem cookies do cliente): home com gráfico, configurações, módulos
 *  - Mobile 360 com sessão do CLIENTE (não do admin)
 *  - Domínios com resultados reais em ambos os tamanhos
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
  const wpLogin = async (page, user, pass) => {
    await go(page, BASE + '/wp-login.php');
    await new Promise((r) => setTimeout(r, 500));
    await page.type('#user_login', user);
    await page.type('#user_pass', pass);
    await Promise.all([
      page.click('#wp-submit'),
      page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
    ]);
  };
  const clientLogin = async (page) => {
    await go(page, BASE + '/entrar/');
    await page.type('input[name="log"]', 'cliente@lagos.com');
    await page.type('input[name="pwd"]', 'cliente123');
    await Promise.all([
      page.click('button[type="submit"]'),
      page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
    ]);
  };

  // ── CONTEXTO 1: ADMIN (desktop, cookies limpos) ──
  const ctxAdmin = await browser.createBrowserContext();
  const adm = await ctxAdmin.newPage();
  await adm.setViewport({ width: 1440, height: 900 });
  await wpLogin(adm, 'eduardo', 'LagosPanel#2026');
  console.log('admin logado em:', adm.url());
  await go(adm, BASE + '/wp-admin/admin.php?page=lagospanel');
  await shot(adm, '10-admin', { fullPage: true, wait: 1400 });
  await go(adm, BASE + '/wp-admin/admin.php?page=lagos-settings');
  await shot(adm, '10b-admin-config', { fullPage: true, wait: 900 });
  await go(adm, BASE + '/wp-admin/admin.php?page=lagos-modules');
  await shot(adm, '22-admin-modulos', { fullPage: true, wait: 900 });
  await ctxAdmin.close();

  // ── CONTEXTO 2: CLIENTE, MOBILE 360 ──
  const ctxClient = await browser.createBrowserContext();
  const cli = await ctxClient.newPage();
  await cli.setViewport({ width: 360, height: 740, deviceScaleFactor: 2 });
  await cli.goto(BASE + '/', { waitUntil: 'networkidle0' }).catch(() => {});
  await shot(cli, '37-mobile360-home');
  await go(cli, BASE + '/verificar-dominio/?domain=lagossolucoes');
  await shot(cli, '38-mobile360-dominios', { wait: 3500 });
  await clientLogin(cli);
  console.log('cliente logado em:', cli.url());
  await go(cli, BASE + '/painel/');
  await shot(cli, '39-mobile360-painel');
  await go(cli, BASE + '/painel/faturas/');
  await shot(cli, '40-mobile360-faturas-cards');
  await ctxClient.close();

  // ── CONTEXTO 3: DOMÍNIOS DESKTOP (público) ──
  const ctxPub = await browser.createBrowserContext();
  const pub = await ctxPub.newPage();
  await pub.setViewport({ width: 1440, height: 900 });
  await go(pub, BASE + '/verificar-dominio/?domain=lagossolucoes');
  await shot(pub, '31-dominios', { fullPage: true, wait: 3500 });
  await ctxPub.close();

  await browser.close();
  console.log('DONE');
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
