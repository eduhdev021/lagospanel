# 🌊 LagosPanel

**O painel de billing de nova geração** — propriedade exclusiva da **Lagos Soluções** © 2026
([termos completos em LICENSE.md](LICENSE.md) — todos os direitos reservados).
Construído para competir com WHMCS e Paymenter: mais moderno, mais rápido e bilíngue (PT-BR 🇧🇷 / EN 🇺🇸).

## 🚀 Acesso

| O quê | Onde | Credenciais |
|-------|------|-------------|
| Site público | `/` | — |
| Loja | `/loja/` | — |
| Área do cliente | `/painel/` | cadastre-se em `/registrar/` (o pacote de produção vem sem dados demo) |
| Cadastro | `/registrar/` | — |
| Administração WP | `/wp-admin/` | `eduardo` / `LagosPanel#2026` |

## 🧱 Arquitetura

```
lagospanel/
├── public/                  # WordPress 7.1.2 (raiz do site)
│   └── wp-content/
│       ├── themes/lagospanel/      # TEMA — design system + templates
│       │   ├── assets/panel.css    # CSS completo (site + painel)
│       │   ├── assets/panel.js     # JS (sidebar mobile, copiar, modal Pix)
│       │   ├── front-page.php      # Landing page (hero, features, preços)
│       │   ├── template-painel.php # Shell da área do cliente (sidebar)
│       │   ├── single-lagos_product.php  # Página de produto da loja
│       │   └── ...                 # header, footer, page, single, index
│       └── plugins/lagos-core/     # PLUGIN — o coração do painel
│           ├── includes/i18n.php       # Dicionário PT-BR/EN (~230 chaves)
│           ├── includes/post-types.php # CPTs: produto, serviço, fatura, ticket
│           ├── includes/shortcodes.php # Dashboard, serviços, faturas+Pix,
│           │                            # suporte, loja, perfil, login/cadastro
│           ├── includes/auth.php       # Login/cadastro front-end + papel cliente
│           ├── includes/admin.php      # Menu admin unificado + configurações
│           ├── includes/helpers.php    # Dinheiro, badges, QR Pix fake, API keys
│           └── includes/seed.php       # Dados de demonstração
├── data/mysql/              # Banco MariaDB (PERSISTENTE entre sessões)
├── scripts/                 # setup.sh + install-wp.php
└── router.php               # Router do servidor PHP embutido
```

## ⚙️ Funcionalidades (v0.10)

- ✅ **Área do cliente** com dashboard (serviços, faturas, tickets, saldo)
- ✅ **Loja** com categorias e 6 produtos de demonstração
- ✅ **Fluxo de pedido**: contrata → cria serviço pendente + fatura → pagamento ativa o serviço
- ✅ **Faturas** com modal Pix (QR demo + copia-e-cola)
- ✅ **Central de suporte** com tickets, departamentos, prioridades e respostas
- ✅ **Perfil**: alterar senha, chave de API, idioma, 2FA (em breve)
- ✅ **Multi-idioma** PT-BR/EN com seletor (cookie)
- ✅ **Admin unificado** estilo WHMCS (menu LagosPanel no wp-admin)
- 🆕 **Carrinho multi-item** com quantidades e checkout em fatura única
- 🆕 **Cupons de desconto** (percentual ou fixo) — demo: `LAGOS10` e `BOASVINDAS5`
- 🆕 **API REST v1** (`/wp-json/lagos/v1`) — products (público), me, services, invoices, tickets (GET/POST) com header `X-Lagos-Key`
- 🆕 **Modo escuro** 🌙 com alternância instantânea (botão da lua no header/painel)
- 🆕 **Identidade visual roxa oficial** (Lagos Soluções) com logos integradas
- 🆕 **Sistema de módulos de provisionamento** ⚡ estilo WHMCS:
  - 🎮 **Pterodactyl** — cria/suspende/remove servidores de jogo
  - 🖥️ **cPanel/WHM** — contas de hospedagem (createacct/suspendacct/...)
  - 🅰️ **aaPanel** — sites (AddSite/SiteStop/DeleteSite)
  - ☁️ **Virtualizor** — VPS (addvs/suspend/delete)
  - 🌐 **Cloudflare DNS** — zonas DNS ao contratar domínios
  - 🔧 **API Personalizada** — endpoints próprios (webhooks)
  - Modo simulado para demos, teste de conexão, log de provisionamento e ações manuais
- 🆕 **Serviços estilo Paymenter (melhor)**: cards com ícone por módulo, tela de gerenciamento com conexão/ID remoto, renovação automática (toggle) e solicitação de cancelamento
- 🆕 **Segurança estilo Paymenter**: 2FA real (TOTP, RFC 6238 — Google Authenticator/Authy) e sessões ativas com encerramento remoto
- 🆕 **SMTP configurável** + e-mails transacionais (boas-vindas, fatura nova, pagamento, recarga, tickets) com log
- 🆕 **Base de conhecimento** pública com busca e categorias
- 🆕 **Carteira com recarga**: fatura de recarga via Pix credita saldo automaticamente
- 🆕 **Zero emojis na UI** — ícones SVG profissionais (estilo Font Awesome, inline: funciona offline)
- 🆕 **v0.5 — Mobile Perfection**: painel perfeito em qualquer celular
  - Auditoria automatizada de overflow horizontal em 360/390/768px — **14 páginas, zero quebras**
  - Tabelas viram cards empilhados com rótulos (`data-label`) abaixo de 620px
  - Header e topbar compactos por breakpoint, modais viram bottom-sheet
  - Inputs com `font-size:16px` (evita zoom automático do iOS) e alvos de toque ≥36px
- 🆕 **v0.5 — Busca de domínios** (`/verificar-dominio/`): consulta RDAP real (registro.br, Verisign, rdap.org) com cache de 10 min, botões Registrar/Transferir
- 🆕 **v0.5 — Programa de afiliados** (`/painel/afiliados/`): link `?ref=LAG-xxxxxx` com cookie de 30 dias, comissão configurável no admin (padrão 10%) creditada na carteira quando a fatura do indicado é paga, com histórico
- 🆕 **v0.5 — Downloads** (`/downloads/`): central de arquivos por produto (CPT dedicado, gerenciável no admin)
- 🆕 **v0.5 — Status da rede** (`/status/`): página pública estilo status page com componentes e histórico de incidentes
- 🆕 **v0.5 — Avaliação de tickets**: cliente avalia o atendimento com 1–5 estrelas após o fechamento; média exibida no dashboard admin
- 🆕 **v0.5 — Admin dashboard**: gráfico de faturamento mensal (SVG), MRR estimado, satisfação média e atalhos
- 🆕 **v0.5 — PWA**: manifest + theme-color + ícones (instalável no celular)
- 🆕 **v0.5 — Logo branca** automaticamente em superfícies escuras (dark mode e sidebar)
- 🆕 **v0.5 — Correção**: página admin "Conexões & Módulos" inacessível desde a v0.3 (hook registrado antes do menu pai) — corrigida
- 🆕 **v0.6 — Gateways de pagamento (os mesmos tipos do Paymenter)**:
  - **Stripe** — Checkout Session (cartão, Apple Pay, Google Pay)
  - **PayPal** — Orders v2 + OAuth2 (sandbox/produção)
  - **Mollie** — Payments API (iDEAL, cartão, métodos europeus)
  - **Mercado Pago** — Pix com QR real na tela ou Checkout Pro (cartão/boleto)
  - **PagSeguro (PagBank)** — Pix via Orders API, QR + copia-e-cola na tela
  - **Gerencianet** — Pix via API oficial de cobranças (cob/QR, certificado mTLS .p12)
  - **Coinbase Commerce** — criptomoedas (BTC, ETH, USDC)
  - **Transferência bancária (manual)** — instruções + confirmação da equipe
  - **Pix demo** — QR de demonstração (sandbox nativo)
  - Cada gateway tem **modo teste** (sandbox) — homologa sem cobrar de verdade
  - **Webhooks verificados** por assinatura (HMAC Stripe/Coinbase, consulta na API no MP/Mollie, verificação oficial PayPal) em `/wp-json/lagos/v1/gateways/{id}/webhook`
  - **Extensível como o marketplace do Paymenter**: classe que estenda `Lagos_Gateway` + filter `lagos_gateways` = novo gateway no admin e no checkout
  - Checkout mobile-first em `/pagamento/`: escolha o método, pague com QR ou redirect, tudo auditado em 360px
- 🆕 **v0.6 — LGPD**: páginas de Termos de Uso e Política de Privacidade (conteúdo completo, editável), checkbox de aceite obrigatório no cadastro e registro do aceite (data + IP) no cadastro do cliente
- 🆕 **v0.6 — Pacote de deploy**: `bash scripts/deploy/build-deploy.sh` gera `deploy/` com arquivos + dump do banco + troca de URL serializada + limpeza de dados demo + guia de instalação (ensaiado de ponta a ponta)
- 🆕 **v0.7 — Segurança de conta & e-mails**: confirmação de e-mail no cadastro (link com expiração de 24h + reenvio), redefinição de senha pelo front-end (anti-enumeration de e-mails), alerta por e-mail quando a conta for acessada de um IP novo, e-mails de boas-vindas/fatura/pagamento/recarga/ticket/comissão (com log)
- 🆕 **v0.7 — Logs de Auditoria**: eventos sensíveis (login/falha, senha, 2FA, pagamentos, webhooks, gateways, módulos, cron) com usuário, IP e data — página no admin com os últimos 500
- 🆕 **v0.7 — Módulos compatíveis com WHMCS**: **DirectAdmin**, **Plesk**, **Proxmox VE** e **VirtFusion** somados a Pterodactyl, cPanel, aaPanel, Virtualizor, Cloudflare DNS e API custom (10 no total)
- 🆕 **v0.7 — Admin Clientes** estilo WHMCS: avatar (Gravatar), serviços ativos, total pago, carteira, último acesso, 2FA e status de confirmação por cliente
- 🆕 **v0.7 — Gravatar** no perfil e na sidebar do painel
- 🆕 **v0.7.1 — Admin estilo WHMCS**: shell completo nas páginas LagosPanel do wp-admin — topbar roxa com busca global de clientes e sino de notificações (faturas abertas, tickets, transferências), sidebar escura agrupada (Financeiro, Produtos & Serviços, Suporte, Sistema...) e dashboard em tela cheia; o menu nativo do WordPress fica oculto
- 🆕 **v0.7.1 — Propriedade**:
- 🆕 **v0.10 — Opções configuráveis**: cada produto aceita opções estilo WHMCS (slider, select, radio, texto) com acréscimo de preço — ex.: RAM 2/4/8 GB (+R$ 0/20/60) — com total ao vivo na tela do produto, pedido, carrinho e fatura
- 🆕 **v0.10 — SSO**: botão "Abrir painel" na área do cliente — Pterodactyl via credencial rotativa de uso único submetida ao login do painel; cPanel via sessão temporária `create_user_session` (WHM)
- 🆕 **v0.10 — Tickets por e-mail (IMAP)**: respostas de clientes às notificações viram respostas no ticket certo (tag `[LAGOS-#ID]` no assunto, com limpeza de citações); e-mails novos de clientes cadastrados abrem tickets; cliente IMAP embutido (zero dependências), verificação a cada 15 min + botão manual
- 🆕 **v0.9 — Relatórios Financeiros**: página no admin com receita dos últimos 12 meses (gráfico SVG), MRR estimado, ticket médio (ARPU), faturas em aberto/vencidas com dias de atraso, receita por produto/serviço, novos clientes e churn — tudo calculado das faturas reais
- 🆕 **v0.9 — Fatura em PDF**: documento A4 imprimível (botão "Baixar fatura (PDF)" na tela de pagamento → salvar como PDF), bilíngue, com logo, itens, status e equivalência na moeda escolhida pelo cliente
- 🆕 **v0.9 — Webhooks de saída**: notifique sistemas externos quando uma fatura é paga (`invoice.paid`), um serviço é ativado (`service.activated`) ou um cliente se cadastra (`client.created`) — POST JSON assinado com HMAC-SHA256 (`X-Lagos-Signature`) e histórico de entregas no admin
- 🆕 **v0.8 — Multi-moeda**: base BRL com moedas/taxas configuráveis (USD, EUR, GBP...), seletor no topo (R$/$/€), preços, faturas e cobranças convertidos na hora; gateways Pix sempre em BRL; câmbio automático pelas cotações de referência do Banco Central Europeu (Frankfurter) ou manual
- 🆕 **v0.8 — White-label completo**: zero "WordPress" visível — meta/RSD/feeds removidos, login com a marca Lagos, títulos das páginas admin, rodapé próprio, e-mails como LagosPanel, header `X-Redirect-By: LagosPanel`, logo/menu wp.org fora da barra, copy de produtos sem a marca, feed limpo, XML-RPC desligado e `/wp-admin/` redirecionando direto para o dashboard LagosPanel (a base WP segue como engine interna, como Laravel é para o Paymenter) o projeto pertence integralmente à **Lagos Soluções** — LICENSE.md proprietário, copyright nos cabeçalhos e marca "Lagos Soluções" em toda a interface e e-mails
- 🆕 **v0.6 — Correções**: `admin-post.php` era bloqueado para clientes (quebrava formulários do front-end); parâmetro de rota REST vs. campo `id` do corpo JSON nos webhooks

## 🔧 Rodando novamente (sandbox reiniciado)

```bash
bash scripts/setup.sh                      # reinstala pacotes (se necessário)

# Terminal 1 — banco de dados:
/usr/sbin/mariadbd --datadir=/home/user/lagospanel/data/mysql \
  --socket=/tmp/lagos.sock --port=3306 --bind-address=127.0.0.1 \
  --user=user --skip-name-resolve --pid-file=/home/user/lagospanel/data/mysqld.pid

# Terminal 2 — site:
php -S 0.0.0.0:8080 -t /home/user/lagospanel/public /home/user/lagospanel/router.php
```

## 🚀 Indo para produção (checklist)

O que o painel **já faz** sozinho (validado em teste):

- **Modo produção** em Configurações — remove gateways de demonstração do checkout e desliga dados de exemplo
- Pagamento **só** via gateway verificado (checkout `/pagamento/` ou webhook assinado) — o antigo atalho "já paguei" foi removido
- **Automação diária**: faturas de renovação (5 dias antes), marcação de vencidas e suspensão automática (3 dias após vencer, inclusive no painel remoto via módulo)
- Confirmação de transferências manuais pelo admin (1 clique na home)
- Rate limit de login (5 tentativas / 15 min) e `DISALLOW_FILE_EDIT`
- Sandbox explícito por gateway com aviso vermelho no admin quando ativo em produção

O que você (Eduardo) precisa providenciar no servidor:

1. **HTTPS** com certificado válido (Let's Encrypt) e redirecionamento forçado
2. **Credenciais reais** dos gateways escolhidos (Admin → LagosPanel → Pagamentos) e desligar o modo teste
3. **Webhooks** cadastrados nos provedores (as URLs aparecem na configuração de cada gateway)
4. **SMTP real** (Admin → Configurações) + SPF/DKIM no domínio para e-mails não caírem em spam
5. **Cron real do sistema** (recomendado): `define('DISABLE_WP_CRON', true);` no wp-config + `0 3 * * * curl -s https://seusite.com.br/wp-cron.php` no crontab
6. **Backups diários** do banco (MariaDB dump) e dos arquivos `wp-content/uploads`
7. **Revisar os textos legais** (Termos e Privacidade já vêm prontos e editáveis — validar com advogado)
8. Trocar a senha do admin (`eduardo`) no primeiro login e configurar SMTP/gateways/módulos

## 📦 Deploy no servidor

```bash
bash scripts/deploy/build-deploy.sh
```

Gera a pasta `deploy/` com tudo pronto para subir:

| Arquivo | O quê |
|---|---|
| `lagospanel-files.zip` | WordPress + tema + plugin completos |
| `db.sql.gz` | Dump do banco |
| `replace-url.php` | Troca de URL segura para dados serializados |
| `clean-demo.php` | Remove faturas/tickets/conta de demonstração (opcional) |
| `INSTALL.md` | Guia passo a passo (nginx, HTTPS, cron, gateways) |

Bônus: o `wp-config.php` detecta o domínio automaticamente — o site se adapta ao host acessado (com suporte a HTTPS e proxy). O pacote foi **ensaiado**: importou o dump em um banco limpo, trocou a URL e o site migrou com todas as páginas, admin e gateways funcionando.

## 🗺️ Roadmap (próximas versões)

- [x] ~~Gateway Pix real~~ ✅ v0.6 (Mercado Pago + Stripe, PayPal, Mollie, Coinbase, manual)
- [x] ~~Perfeito no mobile (360px)~~ ✅ v0.5
- [x] ~~Afiliados, busca de domínios, downloads, status da rede, avaliação de tickets~~ ✅ v0.5
- [x] ~~Dashboard admin com gráfico~~ ✅ v0.5
- [x] ~~Carrinho de compras e checkout multi-item~~ ✅ v0.2
- [x] ~~Cupons de desconto~~ ✅ v0.2
- [x] ~~API REST pública~~ ✅ v0.2 · ~~Webhooks de saída~~ ✅ v0.9
- [x] ~~Modo escuro~~ ✅ v0.2
- [x] ~~2FA (TOTP)~~ ✅ v0.4
- [x] ~~Provisionamento (Pterodactyl, cPanel, aaPanel, Virtualizor, DNS)~~ ✅ v0.3 (homologar credenciais reais)
- [x] ~~Notificações por e-mail~~ ✅ v0.4 (SMTP + log)
- [x] ~~Multi-moeda (USD/EUR)~~ ✅ v0.8
- [x] ~~Opções configuráveis por produto~~ ✅ v0.10
- [x] ~~SSO para os painéis~~ ✅ v0.10 (Pterodactyl + cPanel)
- [x] ~~Tickets por e-mail~~ ✅ v0.10

---
Feito com 💙 pela **Lagos Soluções** — painel de billing proprietário (ver LICENSE.md).
