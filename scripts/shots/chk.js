const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  await page.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  await new Promise((r) => setTimeout(r, 300));
  await page.type('#user_login', 'eduardo');
  await page.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([page.click('#wp-submit'), page.waitForNavigation({ waitUntil: 'networkidle0' }).catch(() => {})]);
  await page.goto('http://localhost:8080/wp-admin/admin.php?page=lagospanel', { waitUntil: 'networkidle0' });
  const r = await page.evaluate(() => ({
    bodyClass: document.body.className.includes('lagos-shell'),
    wpMenuHidden: getComputedStyle(document.getElementById('adminmenumain')).display === 'none',
    topbar: !!document.getElementById('lagos-topbar'),
    sidebar: !!document.getElementById('lagos-side'),
    sidebarLinks: document.querySelectorAll('#lagos-side a').length,
    overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth,
    bellBadge: (document.querySelector('.lg-bell b') || {}).textContent || 'nenhuma',
  }));
  console.log(JSON.stringify(r, null, 1));
  await browser.close();
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
