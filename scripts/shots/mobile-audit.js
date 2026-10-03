/**
 * LagosPanel — Auditoria mobile: detecta overflow horizontal em cada página
 */
const puppeteer = require('puppeteer-core');
const BASE = 'http://localhost:8080';

const PAGES = [
  '/', '/loja/', '/carrinho/', '/entrar/', '/registrar/', '/base-de-conhecimento/',
  '/verificar-dominio/', '/downloads/', '/status/',
];

(async () => {
  const browser = await puppeteer.launch({
    executablePath: '/usr/bin/chromium',
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'],
  });
  const page = await browser.newPage();

  for (const w of [360, 390, 768]) {
    await page.setViewport({ width: w, height: 740, deviceScaleFactor: 2 });
    console.log(`\n===== ${w}px =====`);
    for (const path of PAGES) {
      await page.goto(BASE + path, { waitUntil: 'networkidle0', timeout: 20000 }).catch(() => {});
      await new Promise((r) => setTimeout(r, 300));
      const r = await page.evaluate(() => {
        const doc = document.documentElement;
        const overflow = doc.scrollWidth - doc.clientWidth;
        // quem está estourando?
        let culprits = [];
        if (overflow > 1) {
          document.querySelectorAll('body *').forEach((el) => {
            const b = el.getBoundingClientRect();
            if (b.right > doc.clientWidth + 2 && b.width > 30) {
              culprits.push((el.className && typeof el.className === 'string' ? el.className.split(' ')[0] : el.tagName) + '(' + Math.round(b.right - doc.clientWidth) + 'px)');
            }
          });
        }
        return { overflow, culprits: culprits.slice(0, 3) };
      });
      const flag = r.overflow > 1 ? `❌ +${r.overflow}px ${r.culprits.join(', ')}` : '✓';
      console.log(`${path.padEnd(24)} ${flag}`);
    }
  }

  // páginas logadas
  await page.setViewport({ width: 360, height: 740, deviceScaleFactor: 2 });
  await page.goto(BASE + '/entrar/', { waitUntil: 'networkidle0' });
  await page.type('input[name="log"]', 'cliente@lagos.com');
  await page.type('input[name="pwd"]', 'cliente123');
  await Promise.all([page.click('button[type="submit"]'), page.waitForNavigation({ waitUntil: 'networkidle0' }).catch(() => {})]);
  for (const path of ['/painel/', '/painel/faturas/', '/painel/servicos/', '/painel/perfil/', '/painel/suporte/']) {
    await page.goto(BASE + path, { waitUntil: 'networkidle0', timeout: 20000 }).catch(() => {});
    await new Promise((r) => setTimeout(r, 300));
    const r = await page.evaluate(() => {
      const doc = document.documentElement;
      const overflow = doc.scrollWidth - doc.clientWidth;
      let culprits = [];
      if (overflow > 1) {
        document.querySelectorAll('body *').forEach((el) => {
          const b = el.getBoundingClientRect();
          if (b.right > doc.clientWidth + 2 && b.width > 30) {
            culprits.push((el.className && typeof el.className === 'string' ? el.className.split(' ')[0] : el.tagName) + '(+' + Math.round(b.right - doc.clientWidth) + ')');
          }
        });
      }
      return { overflow, culprits: culprits.slice(0, 3) };
    });
    const flag = r.overflow > 1 ? `❌ +${r.overflow}px ${r.culprits.join(', ')}` : '✓';
    console.log(`${path.padEnd(24)} ${flag}`);
  }

  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
