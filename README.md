# LagosPanel

**Versão 1.1.0 — distribuição principal.**

Painel de clientes, cobrança, serviços e atendimento, com administração própria, integrações de hospedagem/jogos e assistente Ollama com pesquisa web. Aplicação independente sobre Laravel, mantendo a identidade visual do LagosPanel.

[Baixar versão](https://github.com/eduhdev021/lagospanel/releases/latest) · [Relatar problema](https://github.com/eduhdev021/lagospanel/issues/new/choose) · [Testes](https://github.com/eduhdev021/lagospanel/actions)

> **Antes de operar com clientes reais:** esta publicação como 1.0.0 não certifica integrações autenticadas ou infraestrutura de produção. Os testes automatizados passaram; o catálogo público Ollama foi consultado de verdade. Inferência/pesquisa autenticada, provisionamento real, pagamentos, SMTP externo e MySQL/MariaDB ainda precisam ser validados no ambiente do operador. Consulte [operação](docs/OPERACAO.md) e [escopo](docs/ESCOPO.md).

## Recursos

- Cadastro, verificação de e-mail, recuperação de senha, TOTP/2FA e permissões de equipe.
- Catálogo, carrinho, opções de produto, cupons, estoque reservado e pedidos idempotentes.
- Faturas/PDF não fiscal, carteira e pagamentos em BRL; rotinas de renovação e cobrança.
- Tickets, prioridades, responsáveis, notas internas, anexos privados, base de conhecimento e respostas prontas.
- Provisionamento cPanel/WHM, aaPanel e Pterodactyl nos recortes documentados; conector HTTPS/JSON próprio.
- Criação de usuários Pterodactyl pelo ADM, vínculo automático após conferência e recuperação de resultado incerto sem repetir a criação. Não é criação automática no checkout.
- Integrações Stripe e Mercado Pago, desativadas por padrão até configuração e validação operacional.
- Chat Ollama com chave e modelo no ADM, conversas privadas, fila e consentimento.
- Pesquisa web mediada pelo painel, com consulta pública e fontes, sem depender de tool calling nativo do modelo.
- Instalador pelo navegador e configuração administrativa de nome, URL, logo, contato, cadastro público e SMTP.

O catálogo completo do que existe e do que falta está na [matriz de funcionalidades](docs/MATRIZ-PARIDADE.md). Não há declaração de paridade integral com WHMCS/Paymenter. Dados de sistemas anteriores não são importados automaticamente.

## Instalação

PHP **8.2+**, Composer 2 e extensões ctype, curl, dom, fileinfo, filter, hash, intl, mbstring, openssl, pdo, session, tokenizer e xml. SQLite para avaliação; MySQL/MariaDB requer validação operacional. Não precisa de Node/Vite/CDN para servir as telas.

1. Prepare domínio/HTTPS e servidor web com DocumentRoot **somente em `public/`**.
2. Extraia o código e prepare o assistente:

```bash
cp .env.example .env
# Ajuste APP_URL e mantenha APP_ENV=production, APP_DEBUG=false e cookies seguros.
bash scripts/prepare-web.sh
```

3. Guarde a chave temporária exibida e abra `https://seu-dominio/instalar`.
4. Configure banco vazio/exclusivo e o administrador inicial. Não há senha padrão.
5. Ative 2FA e configure SMTP/integrações antes de receber clientes.

**Não execute `install.sh` antes do assistente.** A alternativa CLI usa `bash scripts/install.sh` seguido de `php artisan lagos:admin EMAIL`. Leia [instalação web e recuperação](docs/INSTALACAO-WEB.md).

As dependências ficam em **`.cache/vendor`** e são reproduzidas pelo Composer/lockfile. O pacote não inclui dependências, `.env`, chaves, banco, uploads ou credenciais.

### Worker e cron

```bash
php artisan queue:work --tries=3 --timeout=75
# Cron, a cada minuto:
* * * * * cd /caminho/lagospanel && php artisan schedule:run >> /dev/null 2>&1
```

Use supervisor/systemd, fila com retry_after superior ao timeout, backups e monitoramento. [Guia operacional](docs/OPERACAO.md).

### Atualização

Backup de banco, APP_KEY e arquivos privados; instale dependências do lockfile, execute `php artisan migrate --force`, atualize os caches utilizados e reinicie workers. **Não gere outra APP_KEY nem rode o instalador sobre dados existentes.**

## Documentação

- [Configurações do site](docs/CONFIGURACOES-SITE.md)
- [Ollama e pesquisa web](docs/OLLAMA.md)
- [Pterodactyl](docs/PTERODACTYL.md), [aaPanel](docs/AAPANEL.md), [cPanel/WHM](docs/CPANEL.md)
- [Verificação dos contratos de API](docs/API-CONTRACT-REVIEW.md)
- [Escopo e limitações](docs/ESCOPO.md)
- [Licenças e proveniência](THIRD-PARTY-NOTICES.md)

## Testes e problemas

Nesta atualização: **268 testes PHP / 1288 asserções**, **17 cenários concorrentes** e **12 verificações novas de navegador** para criação de contas Pterodactyl. As chamadas de criação foram simuladas; nenhum usuário foi criado em um provedor real. Relatórios das versões anteriores, incluindo 292 verificações de navegador, continuam identificados como evidência anterior — não foram todos reexecutados nesta etapa. Detalhes em [PTERODACTYL-CONTAS.md](docs/PTERODACTYL-CONTAS.md).

```bash
composer install
bash scripts/test.sh
```

Testes de navegador são opt-in e exigem uma demonstração **local e separada**, Playwright/Chromium e servidor em execução. Não rode os fixtures sobre uma instalação de clientes.

Encontrou problema? [Abra uma Issue](https://github.com/eduhdev021/lagospanel/issues/new/choose) com versão, ambiente, passos e erro sanitizado. **Não publique tokens, senhas, `.env`, dados de clientes ou detalhes exploráveis de vulnerabilidades.** Incidentes de segurança precisam de contato privado com o mantenedor.
