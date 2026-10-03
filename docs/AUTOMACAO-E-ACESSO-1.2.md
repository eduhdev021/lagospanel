# Automação e acesso — 1.2.0

## Pterodactyl: conta no pedido pago

No ADM, acrescente `"auto_account":true` ao JSON do produto Pterodactyl. O padrão continua exigir vínculo prévio. O checkout congela plano, destino, e-mail e nomes; sem HTTP e sem criação remota antes da quitação. Exige e-mail verificado, integração ativa e `NATIVE_PROVISIONING_ENABLED=true`. Pedidos de valor zero são quitados pelo faturamento existente.

Após pagamento, a preparação da conta usa job separado do servidor, evitando somar os timeouts. Vínculos existentes são reutilizados; identidade remota é conferida pelo driver antes do servidor. Serviços do mesmo cliente compartilham solicitação persistente e vínculo por integração. Disputa de lease devolve o job à fila por dez segundos, até vinte entregas. Antes de liberar servidor, confere pagamento, serviço não encerrado, destino, identidade e token de execução.

`sent_at` é marcado antes do POST de conta. Depois, somente consultas; nem retomada administrativa repete esse POST. Em caso de timeout/crash, confira ADM → Integrações → Contas Pterodactyl. Em Operações, **Retomar preparação de conta** aparece para criação em revisão ainda sem vínculo e sem envio de servidor. Requer `operations.manage`, `services.manage`, CSRF e justificativa. Consulta solicitação anterior ou usa vínculo conferido; se não confirmado, permanece em revisão.

Cancelar localmente não desfaz uma operação remota já iniciada. Não há promessa de exactly-once distribuído. Convite/recuperação dependem do SMTP Pterodactyl, sem garantia de entrega. Senha LagosPanel nunca é enviada.

## Pterodactyl: energia

Em Meus serviços, gerencie um serviço ativo. Informe a **Client API Key da conta vinculada**, senha LagosPanel, TOTP novo quando ativo e confirme a interrupção. Chave usada apenas nesta solicitação: não persistida, não reaparece no HTML nem na auditoria. Não use Application Key nem Client API Key de administrador.

Confere `/api/client/account` (ID/e-mail e `admin=false`), depois usuário/servidor pela Application API (titular, external_id, ID remoto, plano, UUID), antes de enviar o sinal. Revalida estado local/integração antes de cada chamada Client API. Suspensão, instalação e operações de provisionamento não resolvidas bloqueiam. Somente `start`, `stop`, `restart`; sem `kill` ou console arbitrário.

`pterodactyl_controls` guarda chave idempotente por cliente, serviço, sinal, estado e marcador de envio, nunca credenciais. Mesma chave não repete comando; reutilização com sinal diferente é recusada. Solicitações simultâneas recentes são bloqueadas. Timeout após marcador fica `uncertain`; sem retry automático. Confira o provedor antes de novo comando explícito. Interrupção fatal pode deixar `processing`, sem autorizar repetição daquela solicitação. A chave não entra em fila.

HTTP 204 significa **comando aceito**, não prova de processo ligado/parado. Situação financeira não muda. Sem console, telemetria contínua, SSO Pterodactyl, gestão de backups, reinstalação ou upgrade.

## cPanel: sessão temporária

Meus serviços → **Entrar no cPanel** exige senha LagosPanel/TOTP novo quando ativo. Não depende da senha inicial nem a envia. Token WHM precisa permitir `listaccts` e `create_user_session`. Serviço fixo `cpaneld`: navegador não pode selecionar root, outro usuário ou WHM.

Conta conferida antes/depois da sessão (usuário, domínio, proprietário, pacote, e-mail, suspensão). Destino deve coincidir com origem HTTPS `client_url` do contrato, sem credenciais/fragmento, caminho `cpsess…/login/`, sessão do usuário certo e expiração futura. URL reconstruída sem parâmetros arbitrários. Redirecionamento 303, `no-store`, `no-referrer`; sessão/URL não persistidas em auditoria, fila ou flash. O navegador recebe o endereço autenticado para entrar.

`client_url` deve corresponder à origem retornada pelo WHM, geralmente porta 2083. Proxy 443 divergente será recusado, não reescrito silenciosamente. Sessão temporária não significa uso único. Não há atomicidade entre mudanças administrativas remotas e leituras locais; conferência posterior reduz, mas não elimina, corridas externas.

## Fontes e validação

Código oficial Pterodactyl `1.0-develop`: `Application/ServerTransformer.php`, `Client/AccountTransformer.php`, `Client/AccountController.php`, `Client/Servers/PowerController.php` em https://github.com/pterodactyl/panel/tree/1.0-develop/app. WHM 11.138.0.10: https://api.docs.cpanel.net/openapi/whm/operation/create_user_session/ e OpenAPI oficial. Transporte WHM mantém POST conforme cliente oficial, embora OpenAPI descreva GET; homologação no provedor continua necessária.

APIs simuladas nos testes. Nenhum servidor real reiniciado, sessão WHM real emitida ou SMTP real enviado. Estilos/assets e layout-base preservados; controles adicionados nos componentes existentes.

## Reproduzir navegador

Prepare/instale uma cópia local isolada sob `.cache/`, com dependências e banco exclusivo. Nela execute `LAGOS_TEST_MODE=1 php tests/controls-seed.php` (uma vez; não é seed de produção). Inicie `LAGOS_TEST_MODE=1 php -S 127.0.0.1:8084 -t public tests/controls-router.php`, usando a mesma URL configurada na instalação. No repositório original execute `LAGOS_TEST_MODE=1 LAGOS_TEST_URL=http://127.0.0.1:8084 LAGOS_CONTROLS_TEST_ROOT=/caminho/.cache/copia python tests/browser_controls.py`, com Playwright/Chromium instalados. A pasta isolada guarda senha aleatória apenas como fixture privada. O navegador valida a resposta 303 do SSO; não certifica login em cPanel externo.
