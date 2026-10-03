# 🌊 LagosPanel

Painel de billing da **Lagos Soluções** — loja, faturas, provisionamento automático e suporte, no padrão WHMCS. Bilíngue (PT-BR/EN), identidade roxa, mobile perfeito.

> © 2026 Lagos Soluções — Todos os direitos reservados. Código proprietário (ver [LICENSE.md](LICENSE.md)).

## 📁 Estrutura (estilo Paymenter)

```
lagospanel/
├── app/                      # LagosPanel Core (o produto)
│   ├── lagos-core.php        # bootstrap
│   ├── Admin/                # dashboard, clientes, auditoria, relatórios
│   ├── Console/              # rotinas diárias (renovações, suspensões, câmbio)
│   ├── Extensions/
│   │   ├── Gateways/         # 9 gateways (Pix/MP, Stripe, PayPal, ...)
│   │   └── Modules/          # 10 módulos (Pterodactyl, cPanel, ...)
│   ├── Http/                 # API REST pública
│   ├── Mail/                 # SMTP + tickets por e-mail (IMAP)
│   ├── Security/             # login, 2FA, sessões
│   ├── Services/             # loja, carrinho, afiliados, webhooks
│   ├── Setup/                # CPTs, seed, páginas legais
│   └── Support/              # helpers, i18n PT/EN, ícones, white-label
├── resources/                # tema LagosPanel (views do cliente)
├── engine/                   # base WordPress empacotada (como o vendor/ do Laravel)
├── database/
│   ├── seed.sql.gz           # banco inicial (loja pronta, sem dados demo)
│   ├── replace-url.php       # troca de URL segura (dados serializados)
│   └── clean-demo.php        # utilitário: zera dados de demonstração
├── public/                   # docroot — montado pelo install.sh ou scripts/dev-setup.sh
├── scripts/
│   ├── dev-server.php        # roteador do php -S (desenvolvimento)
│   └── dev-setup.sh          # monta public/ + sobe MariaDB + servidor dev
├── install.sh                # instalador de produção (Ubuntu/Debian)
├── README.md
└── LICENSE.md
```

O repositório contém **apenas o produto**. A engine entra empacotada em `engine/` e é montada em `public/` pelo instalador — do mesmo jeito que o Paymenter não versiona o framework Laravel.

## 🚀 Instalação (produção)

Requisitos: Ubuntu/Debian limpo, domínio apontando para o servidor.

```bash
git clone https://github.com/eduhdev021/lagospanel.git
cd lagospanel
sudo ./install.sh --domain painel.seudominio.com.br --ssl
```

O instalador instala nginx + MariaDB + PHP-FPM, importa o banco, monta o `public/`, gera o `wp-config.php` com chaves próprias, aplica as URLs do seu domínio, HTTPS (Let's Encrypt) e o cron. No fim, mostra as credenciais.

## 🧰 Desenvolvimento

```bash
# rodar sem nginx (homologação rápida)
php -S 0.0.0.0:8080 scripts/dev-server.php
```

## ⚙️ Funcionalidades

- **Loja e checkout**: produtos com opções configuráveis (estilo WHMCS — slider/lista/texto com acréscimo de preço), carrinho, cupons, domínios, afiliados
- **Faturamento**: faturas com renovação automática, suspensão por inadimplência, fatura em PDF, multi-moeda (BRL base + USD/EUR/GBP com câmbio BCE), saldo/créditos
- **9 gateways**: Pix (Mercado Pago), PagSeguro, Gerencianet, Stripe, PayPal, Mollie, Coinbase, manual — todos com verificação real (webhook assinado/consulta)
- **10 módulos de provisionamento** (compatíveis com os mesmos painéis do WHMCS): Pterodactyl, cPanel, aaPanel, Virtualizor, Cloudflare DNS, DirectAdmin, Plesk, Proxmox, VirtFusion, API custom
- **SSO 1-clique** no Pterodactyl (credencial rotativa) e cPanel (sessão WHM)
- **Suporte**: tickets com **resposta por e-mail** (IMAP embutido, tag `[LAGOS-#ID]`), base de conhecimento, incidentes de status
- **Admin estilo WHMCS**: dashboard, CRUDs de clientes/produtos/serviços/faturas, **relatórios financeiros** (receita 12 meses, MRR, churn, inadimplência), auditoria com export CSV
- **Integrações**: API REST pública (chave por cliente), webhooks de saída assinados (HMAC-SHA256)
- **Segurança**: 2FA opcional (TOTP), confirmação de e-mail, alerta de novo IP, rate limit, headers, sessões
- **White-label**: nenhum "WordPress" visível em nenhuma tela, login/e-mails/rodapés com a marca Lagos

## 🔐 Primeiros passos após instalar

1. Trocar a senha do admin (`eduardo`)
2. Configurações → SMTP, moedas/câmbio, modo produção
3. Gateways → credenciais reais (Mercado Pago, Stripe...)
4. Conexões → módulos reais (Pterodactyl, cPanel...)

---

Feito com 💜 pela **Lagos Soluções**.
