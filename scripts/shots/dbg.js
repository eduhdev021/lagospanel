const puppeteer = require('puppeteer-core');
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/chromium', headless: 'new', args: ['--no-sandbox','--disable-gpu','--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  await page.goto('http://localhost:8080/wp-login.php', { waitUntil: 'networkidle0' });
  console.log('login page title:', await page.title());
  await page.type('#user_login', 'eduardo');
  await page.type('#user_pass', 'LagosPanel#2026');
  await page.click('#wp-submit');
  await page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 20000 }).catch((e) => console.log('nav err:', e.message));
  console.log('após login URL:', page.url());
  console.log('após login title:', await page.title());
  const err = await page.$eval('#login_error', (el) => el && el.textContent).catch(() => null);
  console.log('erro:', err && err.trim());
  await page.goto('http://localhost:8080/wp-admin/admin.php?page=lagospanel', { waitUntil: 'networkidle0' });
  console.log('admin URL:', page.url());
  console.log('admin title:', await page.title());
  await browser.close();
})();
