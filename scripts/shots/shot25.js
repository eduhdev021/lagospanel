const puppeteer = require('puppeteer-core');
(async () => {
  const b = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });
  const p = await b.newPage();
  await p.setViewport({ width: 1440, height: 900 });
  const go = (u) => p.goto(u, { waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {});
  await go('http://localhost:8080/entrar/');
  await p.type('input[name="log"]', 'cliente@lagos.com');
  await p.type('input[name="pwd"]', 'cliente123');
  await Promise.all([p.click('button[type="submit"]'), p.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await new Promise(r => setTimeout(r, 800));
  const hasCode = await p.$('input[name="code"]');
  if (hasCode) {
    await p.type('input[name="code"]', process.env.TOTP_CODE);
    await Promise.all([p.click('button[type="submit"]'), p.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  }
  await go('http://localhost:8080/painel/servicos/?view=' + process.env.SVCID);
  await new Promise(r => setTimeout(r, 900));
  await p.screenshot({ path: '/home/user/lagospanel/screenshots/25-servico-detalhe.jpg', type: 'jpeg', quality: 85, fullPage: true });
  console.log('ok 25-servico-detalhe');
  await b.close();
})().catch(e => { console.error('ERRO:', e.message); process.exit(1); });
