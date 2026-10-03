# Operação e corte para produção

Esta distribuição é **1.3.0**. A publicação estável não substitui validação operacional: consulte as limitações de `ESCOPO.md` antes de operar com clientes reais.

## Preparação

1. Configure um domínio real com HTTPS; exponha **apenas `public/`**. Nunca sirva a raiz do projeto, `.env`, `.cache`, banco, storage ou backups.
2. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`. Configure os proxies confiáveis no ambiente de implantação antes de depender de cabeçalhos encaminhados.
3. Troque `MAIL_MAILER=log` por SMTP homologado. O modo `log` salva links sensíveis em `storage/logs/mail.log`, **não envia e-mail**. Proteja/perfaça a rotação desses arquivos; nunca publique-os.
4. Faça a homologação em MariaDB/MySQL com InnoDB antes de assumir resultados de concorrência equivalentes ao SQLite.
5. Use um usuário SQL de aplicação sem permissões administrativas desnecessárias. Permissões de escrita somente nos diretórios de runtime e banco de avaliação.
6. Gere `APP_KEY` uma única vez e faça backup seguro. Rotacioná-la sem planejamento impede ler tokens de conectores, segredos 2FA e sessões já criptografados.
7. Crie administrador por `php artisan lagos:admin EMAIL`, habilite 2FA e guarde os códigos de recuperação.
8. Atualize as informações da instalação/termos e a política de privacidade antes de aceitar clientes reais.

## Worker e cron

`php artisan queue:work --tries=3 --timeout=75` sob supervisor. O job remoto define uma tentativa e timeout próprio para não repetir criações cegamente. E-mails podem tentar três vezes. O intervalo de retry da fila deve exceder o timeout do worker.

Cron a cada minuto: `php artisan schedule:run`. A manutenção roda de hora em hora, com lock de sobreposição: gera faturas cinco dias antes do vencimento e considera suspensão remota após três dias de atraso. Serviços manuais exigem intervenção da equipe. A manutenção também libera reservas expiradas de pedidos novos e agenda lembretes financeiros deduplicados. Consulte `COMERCIO-E-PERMISSOES.md` antes de alterar prazos.

Monitorar: `jobs`, `failed_jobs`, `operations` em `review`/`processing` parados, vencidas, conciliação de recebimentos, logs e espaço em disco. Reprocessar e-mail pode duplicar notificação. Não aplicar `queue:retry all` indiscriminadamente a operações de provisionamento incertas.

```bash
php artisan lagos:doctor
php artisan lagos:doctor --production
php artisan queue:failed
php artisan schedule:list
```

O primeiro diagnóstico valida conectividade e configuração básica. O segundo cobra HTTPS, modo de produção, SMTP e 2FA da equipe, mas não verifica entrega real, compatibilidade remota ou um backup restaurável.

## Atualização / backup / rollback

Antes de alterações: colocar a aplicação em manutenção, interromper workers, fazer snapshot consistente do banco, copiar arquivos enviados e guardar a chave/segredos fora da pasta pública. Validar a restauração numa instalação isolada. Não existe mecanismo de backup automático nesta alpha. Migrations acrescentam tabelas e colunas; nunca execute `migrate:fresh` no banco de operação.

Aplicar dependências a partir de `composer.lock`, executar migrations revisadas e reiniciar workers. **Não rode `key:generate` em atualizações.** Preserve um par compatível de código e banco para rollback; restaurar somente código após migration não garante consistência.

## Dados anteriores

Nenhuma importação foi executada. Manter a instalação anterior intacta até existir exportação validada, tabela de correspondência, soma de faturas/pagamentos/saldos reconciliada, vínculos de serviços conferidos e teste completo de recuperação de acesso. Os campos preparados para IDs anteriores não constituem um migrador.


## Atualização alpha.1 → alpha.2

Faça backup e manutenção primeiro. Instale o código novo, execute `composer install --no-dev`, limpe os caches de configuração/rotas/views, rode `php artisan migrate --force`, valide o diagnóstico e reinicie workers. Os scripts de instalação agora limpam esses caches sem tentar apagar tabelas/cache do banco antes de criar uma instalação nova.

Produtos anteriores preservam preço/ciclo; quantidade continua permitida e limites por cliente ficam vazios. Pedidos antigos não recebem reservas fictícias nem prazos retroativos. A demonstração independente foi migrada e os fluxos antigos reexecutados; isso não é importação de dados de outro painel.

`APP_ENV=production` exige que operadores com acesso administrativo tenham 2FA. Novo operador configura no próprio perfil. As permissões são conferidas nas rotas, não apenas nos menus.

API e relatórios devem ser publicados somente com HTTPS, limites de borda, banco protegido e política de acesso/retenção. Tokens e credenciais da demonstração não integram o ZIP.


## Atualização alpha.2 → alpha.3

Faça backup consistente do banco e guarde `APP_KEY` separadamente, em segredo. Instale dependências do lockfile e execute `php artisan migrate --force`, `php artisan config:clear`, `php artisan route:clear`, `php artisan view:cache`. Reinicie os workers após atualizar o código. Não execute `key:generate` sobre uma instalação existente.

A migration `000006_expand_support` acrescenta campos de atendimento e uma tabela de anexos. Chamados antigos mantêm suas mensagens e não recebem atrasos retroativos inventados. A política de arquivos, quotas, memória, filas e limitações está em `ATENDIMENTO.md`. Antes de aceitar arquivos em produção, estabeleça antivírus, retenção, monitoramento e restauração testada. O rollback desta migration é bloqueado quando existem anexos ou notas internas, para evitar perda de arquivos ou exposição de notas pelo código antigo. Não remova essa proteção: planeje backup/restauração e preservação de dados antes de qualquer downgrade. Não use `migrate:rollback` como atualização rotineira.


## Atualização alpha.3 → alpha.4

Preserve banco e `APP_KEY`, instale o lockfile, aplique `php artisan migrate --force`, limpe caches de configuração/rotas e recompile views. Reinicie os workers para carregar os novos drivers e a identificação de execução. A migration `000007_native_provisioning` mantém conectores antigos como `json`; não converte serviços antigos para cPanel.

Exige extensão cURL. `NATIVE_PROVISIONING_ENABLED=false` por padrão; o bloqueio é de chamadas remotas, não de vendas. Deixe produtos nativos indisponíveis até homologar. Os jobs possuem timeout de 60 segundos; mantenha `DB_QUEUE_RETRY_AFTER` acima disso (padrão 90). Ajuste supervisão e monitore operações em revisão. Nunca habilite retries automáticos de mutações WHM.

Leia `CPANEL.md` antes de habilitar o driver. Encerrar uma conta WHM apaga dados e DNS (`keepdns=0`); faça backups externos ao painel. Downgrade é bloqueado quando houver vínculos nativos. O teste de instalação/roundtrip vazio não substitui restauração operacional com dados.

O script de testes inclui as quatro suites de navegador quando `LAGOS_BROWSER_TESTS=1`. A suite nativa usa um IP reservado via header apenas na demonstração LOCAL, para isolar seu orçamento de login; não altera limites da aplicação. Não execute fixtures contra dados reais.

## Atualização alpha.6

Faça backup do banco, fontes e APP_KEY. Reinstale dependências com `composer install --no-dev --prefer-dist --optimize-autoloader`, aplique `php artisan migrate --force` e reconstrua caches/reinicie workers. São 11 migrations em instalação nova. A nova migration cria apenas a biblioteca de respostas prontas; rollback com registros é bloqueado para evitar perda silenciosa. PDF exige ext-dom/ext-mbstring (resolvidos pelo Composer), e escrita privada em `storage/app/private/pdf-runtime`. Não expor essa pasta. Não gerar outra APP_KEY.

## Atualização alpha.8

Faça backup do banco e APP_KEY, instale dependências do lockfile e aplique migrations/caches; reinicie workers. Instalação nova tem 12 migrations. A migration 000009 cria vínculos Pterodactyl, configuração Ollama, conversas/mensagens e cotas diárias. Não converte usuários remotos nem habilita IA por padrão. Rollback com vínculos/conector Pterodactyl, configuração ou mensagens é bloqueado.

Mantenha worker persistente na fila `default`. Pterodactyl consulta instalação sem mutações até 20 vezes, em intervalos de 30 segundos mais latência/fila. IA usa uma tentativa, timeout de job 55s e HTTP 45s; não habilite retries automáticos. Catalogar modelos é ação síncrona administrativa com timeout de 10s. Mantenha retry_after >60s (padrão90). Separe/capacite workers conforme carga para evitar que inferências atrasem cobrança/provisionamento; ainda não há isolamento de filas por recurso ou benchmark de capacidade.

Monitorar `ai_turns` queued/processing paradas. Ao enviar nova mensagem, pendências locais do mesmo usuário com mais de cinco minutos são encerradas como falha, sem reenviar; resultados atrasados ficam bloqueados. Exclusão da conversa não cancela uma inferência já enviada e não apaga dados retidos pelo provedor/backups. Cotas e criptografia não substituem LGPD, retenção ou controle de custo no provedor.

Agora há sete suites de navegador no modo opt-in. Fixtures só em demonstração LOCAL; nunca em produção. Consulte `PTERODACTYL.md` e `OLLAMA.md` para configuração, contratos e homologação.


## Atualização 1.1.0

Executar a migration `2026_10_03_000011_pterodactyl_account_requests` antes de usar a nova tela de contas. Ela adiciona o histórico/controle de solicitações, sem alterar vínculos existentes nem executar chamadas externas. Para criação de usuários pelo ADM, a Application Key precisa também de permissão de escrita em usuários. Chamadas nativas continuam desabilitadas por padrão. Leia `PTERODACTYL-CONTAS.md`.

## Atualização 1.2.0

Execute as migrations pendentes e reinicie os workers após atualizar dependências/caches. A nova tabela `pterodactyl_controls` guarda solicitações de energia e marcadores de envio, nunca Client API Key. São 15 migrations. Preparação de conta usa job próprio (45 s, até 20 entregas em disputa); servidor continua separado (60 s). Após `sent_at`, criação de usuário não é reenviada. Consulte `AUTOMACAO-E-ACESSO-1.2.md`.

## Atualização 1.3.0

Inclui DirectAdmin e Plesk no fluxo nativo. Não há migration nova (15 no total); execute migrations pendentes, caches e restart de workers após atualizar. Planos e integrações existentes não mudam. Leia `DIRECTADMIN-E-PLESK.md`, principalmente versões de API, permissões, IPs e isolamento Plesk gerenciado.
