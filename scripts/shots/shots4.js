/**
 * LagosPanel — Screenshots v0.2.1 (logos oficiais)
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

  // home com nova logo (header claro + footer escuro c/ Lagos Soluções)
  await go(BASE + '/');
  await shot('18-branding-home', { fullPage: true });

  // login (logo do painel no card)
  await go(BASE + '/entrar/');
  await shot('19-branding-login');

  // login cliente → painel (logo no sidebar escuro com chip branco)
  await page.type('input[name="log"]', 'cliente@lagos.com');
  await page.type('input[name="pwd"]', 'cliente123');
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
  ]);
  await shot('20-branding-painel');

  // dark mode home (chip branco no header escuro)
  await go(BASE + '/');
  await page.click('[data-theme-toggle]');
  await shot('21-branding-dark');

  await browser.close();
  console.log('DONE');
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
