/**
 * LagosPanel — Screenshots v0.4 (2FA, sessões, serviços, KB, SMTP)
 */
const puppeteer = require('puppeteer-core');
const crypto = require('crypto');
const BASE = 'http://localhost:8080';
const OUT = '/home/user/lagospanel/screenshots';

function b32decode(s) {
  const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const c of s.replace(/=+$/, '')) bits += a.indexOf(c).toString(2).padStart(5, '0');
  let out = '';
  for (let i = 0; i + 8 <= bits.length; i += 8) out += String.fromCharCode(parseInt(bits.substr(i, 8), 2));
  return Buffer.from(out, 'binary');
}
function totp(secret) {
  const k = b32decode(secret);
  const t = Math.floor(Date.now() / 30000);
  const buf = Buffer.alloc(8);
  buf.writeUInt32BE(t >>> 0, 4);
  const h = crypto.createHmac('sha1', k).update(buf).digest();
  const o = h[19] & 0xf;
  return (((h.readUInt32BE(o) & 0x7fffffff) % 1000000) + '').padStart(6, '0');
}

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

  // ===== SERVIÇOS: grid + detalhe =====
  await loginCliente();
  await go(BASE + '/painel/servicos/');
  await shot('24-servicos-cards', { fullPage: true });

  const detail = await page.$eval('a[href*="view="]', (a) => a.href).catch(() => null);
  if (detail) { await go(detail); await shot('25-servico-detalhe', { fullPage: true }); }

  // ===== ATIVAR 2FA (fluxo real com TOTP) =====
  await go(BASE + '/painel/perfil/');
  await shot('26-perfil-seguranca', { fullPage: true });

  // ===== KB =====
  await go(BASE + '/base-de-conhecimento/');
  await shot('27-kb', { fullPage: true });
  // abre um artigo
  const art = await page.$('details.kb-article summary');
  if (art) { await art.click(); await shot('27b-kb-artigo'); }

  // ===== LOGOUT → tela 2FA =====
  await go(BASE + '/painel/perfil/');
  const logout = await page.$eval('a[href*="action=logout"], a[href*="wplogout"]', (a) => a.href).catch(() => null);
  if (!logout) {
    // sai via URL do template
    const l2 = await page.evaluate(() => document.querySelector('.sidebar-user .icon-btn')?.href);
    if (l2) await go(l2);
  } else { await go(logout); }
  await loginCliente(); // deve parar na tela 2FA
  await shot('28-tela-2fa');

  // completa o 2FA com código calculado
  const secret = await page.evaluate(() => {
    // tenta pegar a chave da página de perfil (sessão anterior) — não disponível aqui,
    // então usa o modo manual: a tela 2FA já mostra campo; pegamos o código do ambiente de teste
    return null;
  });
  // usa o segredo salvo no passo "ativar" via perfil — recarrega perfil não dá (não logado).
  // fallback: injeta o código via evaluate lendo do localStorage? não. Usa código fixo do CLI:
  const code = process.env.TOTP_CODE;
  if (code) {
    await page.type('input[name="code"]', code);
    await Promise.all([
      page.click('button[type="submit"]'),
      page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
    ]);
    await shot('29-painel-pos-2fa');
  }

  // ===== ADMIN: configurações SMTP + log =====
  await go(BASE + '/wp-login.php');
  await new Promise((r) => setTimeout(r, 500));
  await page.type('#user_login', 'eduardo');
  await page.type('#user_pass', 'LagosPanel#2026');
  await Promise.all([
    page.click('#wp-submit'),
    page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 30000 }).catch(() => {}),
  ]);
  await go(BASE + '/wp-admin/admin.php?page=lagos-settings');
  await shot('30-admin-smtp', { fullPage: true, wait: 900 });

  await browser.close();
  console.log('DONE');
})().catch((e) => { console.error('ERRO:', e.message); process.exit(1); });
