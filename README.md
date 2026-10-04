# LagosPanel

> A `main` contém desenvolvimento consolidado **1.4.0-dev**, não uma nova release estável. [Escopo atual e atualização](docs/DESENVOLVIMENTO.md). A paridade integral permanece pendente.
**Última release estável: 1.3.0.**


Painel de clientes, cobrança, serviços e atendimento, com administração própria, integrações de hospedagem/jogos e assistente Ollama com pesquisa web. Aplicação independente sobre Laravel, mantendo a identidade visual do LagosPanel.

[Baixar versão](https://github.com/eduhdev021/lagospanel/releases/latest) · [Relatar problema](https://github.com/eduhdev021/lagospanel/issues/new/choose) · [Testes](https://github.com/eduhdev021/lagospanel/actions)

> **Antes de operar com clientes reais:** esta distribuição não certifica integrações autenticadas ou infraestrutura de produção. Os testes automatizados passaram; o catálogo público Ollama foi consultado de verdade. Inferência/pesquisa autenticada, provisionamento real, pagamentos, SMTP externo e MySQL/MariaDB ainda precisam ser validados no ambiente do operador. Consulte [operação](docs/OPERACAO.md) e [escopo](docs/ESCOPO.md).

## Alterações na main

- Webhooks de saída assinados com outbox persistente, histórico, retentativas e receptor de referência. [Configuração e contrato](docs/WEBHOOKS-SAIDA.md).

- Clientes Plesk isolados após pagamento e acesso temporário do titular, com senha/2FA.
- Orçamentos com aprovação/recusa, validade, revisão e conversão idempotente em fatura avulsa.
- Avisos públicos, incidentes e manutenções com agendamento e histórico.
- Downloads privados criptografados, gerais ou condicionados a serviço ativo de um produto.
- Tema, CSS, imagens e componentes-base preservados; os novos módulos utilizam os componentes existentes.

Leia [módulos comerciais e conteúdo](docs/COMERCIAL-E-CONTEUDO.md). Não substituem funções ainda ausentes da matriz.

## Base da release 1.3.0

DirectAdmin: contas individuais por pacote, criação após pagamento, suspensão/reativação, encerramento, conciliação e acesso inicial protegido. Plesk: assinaturas gerenciadas sob proprietário existente, plano por GUID, ciclo completo de cobrança/provisionamento e credenciais de publicação FTP/FTPS privadas — sem compartilhar login administrativo. [Configuração dos novos painéis](docs/DIRECTADMIN-E-PLESK.md).

## Recursos

- Cadastro, verificação de e-mail, recuperação de senha, TOTP/2FA e permissões de equipe.
- Catálogo, carrinho, opções de produto, cupons, estoque reservado e pedidos idempotentes.
- Faturas/PDF não fiscal, carteira e pagamentos em BRL; rotinas de renovação e cobrança.
- Tickets, prioridades, responsáveis, notas internas, anexos privados, base de conhecimento e respostas prontas.
- Provisionamento cPanel/WHM, aaPanel, Pterodactyl, DirectAdmin e Plesk nos recortes documentados; conector HTTPS/JSON próprio.
- Criação de usuários Pterodactyl pelo ADM, vínculo automático após conferência e recuperação de resultado incerto sem repetir a criação. Preparação automática após pagamento disponível por opção do produto.
- Energia Pterodactyl (ligar/parar/reiniciar) com Client API do cliente, senha/2FA e chave não persistida.
- SSO temporário cPanel com senha/2FA, conferência de identidade e origem HTTPS fixa. [Configuração e limites](docs/AUTOMACAO-E-ACESSO-1.2.md).
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

Na main: **464 testes PHP / 2.757 asserções**, **25 cenários concorrentes**, **9 testes Python do receptor** e **13 verificações Chromium** dos webhooks. As 40 verificações Chromium comerciais são da revisão anterior. APIs de provedores continuam simuladas conforme contratos documentados. Os relatórios anteriores de navegador são históricos; a evidência nova é `docs/BROWSER-WEBHOOKS-RESULTS.json`.

```bash
composer install
bash scripts/test.sh
```

Testes de navegador são opt-in e exigem uma demonstração **local e separada**, Playwright/Chromium e servidor em execução. Não rode os fixtures sobre uma instalação de clientes.

Encontrou problema? [Abra uma Issue](https://github.com/eduhdev021/lagospanel/issues/new/choose) com versão, ambiente, passos e erro sanitizado. **Não publique tokens, senhas, `.env`, dados de clientes ou detalhes exploráveis de vulnerabilidades.** Incidentes de segurança precisam de contato privado com o mantenedor.

### Central de configurações e atualizações

O ADM reúne configurações gerais, e-mail, segurança e ambiente. O 2FA
administrativo é opcional por padrão e pode ser exigido pelo administrador.
A atualização aprovada pelo site exige preparação inicial de um worker não-root,
checkout Git limpo e backup externo; não executa comandos privilegiados pelo PHP-FPM.
Veja [configuração, requisitos e recuperação](docs/ATUALIZACOES-PELO-PAINEL.md).

### Homepage, nova central ADM e login social (1.5.0-dev)

A página inicial pública apresenta os planos reais do catálogo. A central do ADM
possui ícones, busca e categorias responsivas. GitHub, X/Twitter, Facebook, Google
e Microsoft estão integrados para **clientes**, com configuração das credenciais
no ADM e vínculos protegidos por senha/2FA. Os provedores vêm desativados; não há
credenciais de produção nem homologação real embutida.

Veja [configuração e segurança do login social](docs/LOGIN-SOCIAL-E-NOVA-INTERFACE.md).

### Chat e operação (1.6.0-dev)

Chat com envio sem recarregar, histórico, compositor fixo e consentimento por
conversa. Cinco novas seções conectadas aos serviços existentes: faturamento,
automação, gateways, SLA e recursos. Não equivale a todos os módulos do WHMCS.
Veja [funcionamento, limites e testes](docs/CHAT-E-CONFIGURACOES-OPERACIONAIS.md).

### Foto de perfil e diagnóstico da IA

Importação consentida do Gravatar, upload de avatar, estado da chave salva, sinal do worker database e teste de geração autorizado no ADM. Veja [operação e configuração](docs/IA-FOTO-E-OPERACAO.md). Testes com provedores simulados não comprovam funcionamento no servidor de produção.

### Formulários e persistência

Os campos comuns continuam visíveis; somente segredos ficam ocultos. [Validação, preservação de valores e testes](docs/CONFIGURACOES-PERSISTENTES.md).
