# Criação de contas Pterodactyl — 1.1.0

## Usar

1. Atualize o banco com `php artisan migrate --force`. A migration adiciona controle de solicitações; não chama o provedor nem altera vínculos existentes.
2. Na integração Pterodactyl, configure uma **Application Key** com leitura e escrita de usuários, além das permissões de servidores necessárias ao provisionamento. Client Key não serve. Para manter apenas vínculos manuais, escrita de usuários não é necessária.
3. Integração precisa estar ativa e `NATIVE_PROVISIONING_ENABLED=true`. Habilitar chamadas não equivale a homologar a instalação. Confira TLS, firewall e SMTP do Pterodactyl.
4. Em **ADM → Integrações → Contas Pterodactyl**, informe o ID local do cliente, nome e sobrenome e confirme o envio desses dados. Exige `integrations.manage` e `customers.view`, além das proteções administrativas existentes.
5. O cliente deve ter e-mail local verificado. A aplicação gera username e external_id únicos, solicita `root_admin=false` e **não envia uma senha**, muito menos a senha local do cliente.
6. A confirmação de criação e duas leituras concordantes produzem o vínculo local. A contratação já existente pode usar esse vínculo. Não há criação disparada automaticamente pelo checkout nesta versão.

O Pterodactyl gera o acesso inicial e aciona sua notificação de conta. A aceitação da API não comprova entrega do e-mail: configure o SMTP dele, verifique spam/logs e use a recuperação de senha no próprio Pterodactyl se necessário. LagosPanel não promete entrega, não armazena/exibe senha remota e não oferece SSO nesta etapa.

## Duplicações e falhas

- Uma solicitação persistente por integração/cliente. Os dados de destino e identidade ficam preservados e não são substituídos por um reenvio do formulário.
- Transações curtas com trava por integração; chamadas de rede fora delas. Execução identificada impede conclusão de uma execução substituída. SQLite foi testado; MySQL ainda depende de validação operacional.
- Antes do primeiro POST, exige ausência por external_id e resposta404 JSON reconhecida. Conta preexistente com nosso marcador não é adotada automaticamente antes de qualquer envio.
- `sent_at` é gravado **antes** da chamada. Depois disso, jamais repetimos automaticamente o POST, nem quando uma consulta retorna ausência. Interrupção entre gravar sent_at e enviar pode exigir intervenção: preferimos pendência a duplicação.
- **Conferir resultado** só consulta. Se o provedor criou a conta mas houve timeout ou erro de notificação, pode confirmar e vincular depois sem recriar ou reenviar convite.
- Operação ainda em andamento retorna409; depois de dois minutos pode ser retomada para conferência. Isso não cancela uma requisição remota já enviada, nem garante exatamente-uma-vez distribuído.
- Se houve falha antes de registrar envio, a mesma solicitação pode tentar novamente. Após envio com resultado não confirmado, revise no provedor; não apague a solicitação para forçar outro POST.
- Conflito de e-mail/username, identidade divergente, conta administradora ou vínculo ocupado exige revisão. Para uma conta já existente, use vínculo manual com identidade conferida. Ele não apaga possíveis contas órfãs nem sobrescreve vínculos.
- Mudança de endpoint, pausa, alteração de e-mail local ou perda da verificação bloqueiam chamadas/confirmação. Rotação de chave no mesmo endpoint é possível; nenhuma chave é enviada ao navegador.
- Não há edição, exclusão de usuário remoto, reset de senha ou reenvio de convite por este fluxo. Não armazene dados reais em fixtures/testes.

## Contrato conferido

- `GET /api/application/users/external/{external_id}`
- `POST /api/application/users` →201, objeto `user`
- `GET /api/application/users/{id}` →200, objeto `user`

Bearer e `Accept: Application/vnd.pterodactyl.v1+json`; JSON; TLS verificado; sem redirects/retries; conexão3s/resposta8s por chamada, até1MiB. Validação de ID inteiro, external_id, username, e-mail, ausência de root_admin e coerência entre as leituras. O fluxo é síncrono no ADM e pode levar dezenas de segundos; interrupção é tratada como resultado incerto.

Fontes oficiais consultadas em03/10/2026, branch `1.0-develop` (não certifica todos os forks/versões):
- https://github.com/pterodactyl/panel/blob/1.0-develop/app/Http/Requests/Api/Application/Users/StoreUserRequest.php
- https://github.com/pterodactyl/panel/blob/1.0-develop/app/Http/Controllers/Api/Application/Users/UserController.php
- https://github.com/pterodactyl/panel/blob/1.0-develop/app/Services/Users/UserCreationService.php
- https://github.com/pterodactyl/panel/blob/1.0-develop/app/Transformers/Api/Application/UserTransformer.php

## Evidência e reprodução

`PterodactylUsersTest.php`, `BROWSER-PTERO-USERS-RESULTS.json` e `CONCURRENCY-RESULTS.json`. Unit/Feature usa SQLite em memória; concorrência usa SQLite isolado e transporte compartilhado simulado. Browser usa banco `.cache/ptero-users-browser.sqlite` e router de teste com modo local explícito; os formulários chegam ao kernel/middleware/CSRF reais. **Nunca use esse router em produção.**

Nenhuma criação/autenticação em provedor real foi realizada. A criação deve ser homologada com usuário descartável no painel do operador, inclusive falha do SMTP depois do commit remoto.
