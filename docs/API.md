# API v1 — cliente

Prefixo `/api/v1`. Autenticação `Authorization: Bearer TOKEN`. HTTPS obrigatório na implantação; não coloque tokens em URLs, logs, Git ou páginas públicas. Limite de 60 requisições/minuto pelo middleware. Não há acesso administrativo por estes tokens.

| Endpoint | Escopo | Resultado |
|---|---|---|
| `GET /api/v1/services?page=1` | `services:read` | Serviços do próprio cliente; até 50 por página |
| `GET /api/v1/invoices?page=1` | `invoices:read` | Faturas do próprio cliente; até 50 por página |
| `POST /api/v1/tickets` | `tickets:write` | Cria ticket do próprio cliente; retorna 201 e ID |

Ticket JSON: `{"subject":"Ajuda","body":"Descrição","department":"support"}`. Departamento também aceita `billing`. Campos de proprietário enviados pelo cliente não são usados. Criação de ticket ainda não oferece chave de idempotência; evite retries cegos.

## Emissão e revogação

Em Minha conta → Acesso à API: nome, escopos e validade de 1 a 90 dias; máximo de 20 tokens por conta. Exige senha atual e um código TOTP novo quando 2FA está ativo. O token completo aparece apenas após a emissão; armazena-se seu hash.

Revogação é imediata. Expiração, conta não verificada, redefinição obrigatória ou alteração de senha/segredo 2FA invalidam o uso. Tokens pertencem a um único cliente; mesmo um administrador recebe apenas dados de sua própria conta pela API.

401: token ausente/inválido/expirado; 403: escopo insuficiente; 422: dados inválidos; 429: limite de requisições. Respostas de sucesso não devem ser armazenadas em caches compartilhados.

## Ainda não disponível

API administrativa, OAuth, webhooks de saída, compatibilidade com WHMCS/Paymenter, SDKs e automação de terceiros. A emissão/revogação pela interface usa sessão e CSRF; a API usa bearer e não é autenticada por cookie.


Alpha.3: `POST /api/v1/tickets` aceita `priority` opcional (`low`, `normal`, `high`, `urgent`; padrão `normal`) e calcula o prazo pelo mesmo serviço usado na interface. Anexos não são aceitos na API nesta versão (422); use a interface autenticada. Notas internas e download de anexos não são expostos pelos tokens da API.

Alpha.4: a API de cliente continua sem acesso a tokens WHM, snapshots internos ou senha inicial de hospedagem. A revelação de credencial é uma ação web protegida por sessão, senha atual e TOTP novo quando habilitado.
