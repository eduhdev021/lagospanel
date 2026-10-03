const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage','--hide-scrollbars','--font-render-hinting=none'] });
  const shot = async (p, n, full = false) => { await new Promise((r) => setTimeout(r, 900)); await p.screenshot({ path: '/home/user/lagospanel/screenshots/' + n + '.jpg', type: 'jpeg', quality: 85, fullPage: full }); console.log('ok', n); };

  const ctx = await browser.createBrowserContext();
  const c = await ctx.newPage();
  await c.setViewport({ width: 1440, height: 900 });
  await c.goto('http://localhost:8080/entrar/', { waitUntil: 'networkidle0' });
  await c.type('input[name="log"]', 'cliente@lagos.com');
  await c.type('input[name="pwd"]', 'cliente123');
  await Promise.all([c.click('button[type="submit"]'), c.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  // 70: produto com opções (slider em 8 GB + backup Sim → total vivo)
  await c.goto('http://localhost:8080/loja/vps-lagos-cloud-2-gb/', { waitUntil: 'networkidle0' });
  await c.evaluate(() => {
    const sel = document.querySelector('select[data-opt]');
    if (sel) { sel.value = 'Sim'; sel.dispatchEvent(new Event('change', { bubbles: true })); }
    const sl = document.querySelector('.lagos-slider');
    if (sl) { sl.value = 2; sl.dispatchEvent(new Event('input', { bubbles: true })); }
  });
  await shot(c, '70-produto-opcoes', true);
  // 71: serviço com SSO
  await c.goto('http://localhost:8080/painel/servicos/?view=136', { waitUntil: 'networkidle0' });
  await shot(c, '71-servico-sso', true);
  await ctx.close();

  // 72: admin → configurações (IMAP)
  const a = await browser.newPage();
  await a.setViewport({ width: 1440, height: 900 });
  await a.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  await a.type('#user_login', 'eduardo');
  await a.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([a.click('#wp-submit'), a.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {})]);
  await a.goto('http://localhost:8080/wp-admin/admin.php?page=lagos-settings', { waitUntil: 'networkidle0' });
  await shot(a, '72-admin-email-piping', true);
  await a.close();

  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
