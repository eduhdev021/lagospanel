# Webhooks de saída — contrato v1

Implementados no desenvolvimento consolidado da `main` (1.4.0-dev). Não são compatibilidade automática com a API de terceiros, API administrativa, SDK de extensões ou entrega integral da matriz WHMCS/Paymenter.

## Configurar

1. Faça backup do banco e APP_KEY, instale dependências e execute `php artisan migrate --force`. A migration `000015_outgoing_webhooks` cria destinos, outbox e histórico; a instalação completa passa a ter 18 migrations. Rollback com registros é bloqueado.
2. ADM → Integrações → **Webhooks de saída e entregas**. Leitura exige `integrations.view`; criação, ativação e reenvio exigem `integrations.manage`, senha atual e código 2FA novo quando configurado. Em produção, o acesso ADM continua exigindo 2FA.
3. Cadastre URL HTTPS pública de sua propriedade, na porta 443, com nome DNS e caminho opcional, **sem credenciais, query ou fragmento**. Selecione eventos e confirme a autorização. A chave de 64 caracteres hexadecimais aparece somente na resposta de criação, sem cache/Referer; guarde-a no servidor receptor. O segredo é armazenado criptografado, nunca em flash/session ou auditoria. URL/segredo/assinaturas de eventos de um destino não são editáveis: para rotacionar, desative o antigo e crie outro. Não há replay retroativo para o destino novo.
4. Defina `OUTGOING_WEBHOOKS_ENABLED=true`, refaça seu cache de configuração e reinicie os workers. O padrão é **false**. Endpoints ativos capturam eventos mesmo com o envio global pausado; serão processados após habilitação.
5. Mantenha `php artisan schedule:run` a cada minuto e o worker `php artisan queue:work database`. O scheduler executa `lagos:webhooks`, enfileirando até 200 entregas por rodada. Esse comando também pode ser executado manualmente.

Não há chamada HTTP no cadastro nem dentro das transações financeiras. Eventos aprovados são capturados por `Audit::record`: auditoria e outbox são gravados na mesma transação, participando da transação de domínio quando o chamador já a utiliza. O scheduler lê o outbox persistente: interrupção entre commit e enfileiramento não perde uma entrega gravada.

## Eventos e payload

Eventos disponíveis: `invoice.paid`, `order.created`, `service.create`, `service.suspend`, `service.unsuspend`, `service.terminate`, `service.cancellation_requested`, `ticket.created`, `ticket.state`, `quote.accept`, `quote.decline`, `bulletin.created`, `bulletin.updated`.

```json
{
  "schema_version": 1,
  "id": "11111111-2222-4333-8444-555555555555",
  "type": "invoice.paid",
  "occurred_at": "2026-10-03T12:00:00-04:00",
  "data": {"resource": "invoice", "id": 123}
}
```

Somente identificadores de recursos são transmitidos. Contexto da auditoria, corpo de tickets, e-mails, nomes, valores financeiros, textos de avisos, credenciais e dados de cartão não entram no payload. O ID de evento é estável em todas as tentativas da entrega; não use timestamp ou número da tentativa como identificador de negócio. Notificações não garantem ordenação entre eventos diferentes. `occurred_at` é ISO-8601 com offset; a assinatura usa UNIX timestamp.

Headers:

- `Content-Type: application/json`
- `X-Lagos-Event-Id: <UUID>`
- `X-Lagos-Event: <tipo>`
- `X-Lagos-Signature: t=<UNIX timestamp>,v1=<HMAC SHA-256 hexadecimal>`

A entrada do HMAC é `timestamp + "." + corpo JSON bruto`, sem reserializar, usando a chave exibida como **texto ASCII**, não decodificada de hexadecimal. Cada tentativa tem assinatura com timestamp atual; o corpo e ID permanecem iguais.

## Receptor

`examples/webhook_receiver.py` é uma implementação de referência em Python, sem dependências externas. Não é um servidor HTTP nem um SDK completo. Seu servidor deve:

1. Ler os bytes exatos da requisição, com limite de tamanho.
2. Verificar assinatura por comparação constante e timestamp com tolerância de 300 segundos; manter relógios sincronizados.
3. Conferir versão do schema, UUID, tipo e correspondência com os headers. Somente processar tipos esperados pela sua aplicação.
4. Persistir o ID antes de aplicar efeitos, na mesma transação do efeito local. Um evento já processado deve receber 2xx sem repetir o efeito. `apply_once` demonstra isso em SQLite; se o handler falhar, o marcador também é desfeito.
5. Só devolver 2xx depois do commit local. Chamadas a outros serviços precisam de idempotência própria/outbox: uma transação SQLite não torna efeitos remotos exatamente uma vez.

Não inclua chave em JavaScript, URL, exemplos públicos ou logs. Testes do receptor: `python tests/test_webhook_receiver.py` (também executados pelo CI).

## Entrega, falhas e retentativas

- 2xx: `delivered`, significando **aceitação HTTP**, não comprovação de processamento do negócio.
- 408, 429, 5xx e falha de rede: retry, no máximo cinco tentativas iniciais, com intervalos 60/300/1.800/7.200 segundos. `Retry-After` não altera esse backoff fixo.
- 3xx e demais 4xx: falha permanente; redirects nunca são seguidos.
- Destino inválido/DNS sem endereço público: falha fechada, sem HTTP. Corrija a causa e autorize reenvio no ADM.
- DNS é resolvido antes de cada tentativa; todos os endereços IPv4 retornados devem ser globais. O IP escolhido é fixado no cURL, mantendo hostname/SNI e validação TLS. Proxy de ambiente desativado. Loopback, redes privadas, link-local/metadados, CGNAT e faixas reservadas são bloqueados. IPv6-only e rede privada não são suportados neste contrato.
- Conexão: 3 s; HTTP: 10 s; resposta máxima: 64 KiB; job: 30 s. Sem retry automático da biblioteca HTTP.
- Locks, token de execução e lease de 2 min evitam envio duplicado por jobs concorrentes. Após interrupção, outra execução registra a tentativa anterior como `interrupted` e pode repetir a entrega. **Timeout pode acontecer depois de o receptor processar. Deduplicação é obrigatória; não há promessa de exatamente uma vez na rede.**
- Destino desativado: nenhuma captura nova; entregas processadas enquanto estiver desativado viram `cancelled`. Requisições já em trânsito não podem ser recolhidas. Ao reativar, canceladas exigem reenvio manual; não são retomadas silenciosamente.
- Reenvio manual exige motivo, consentimento, reautenticação e destino ativo. Preserva ID/corpo/histórico e autoriza até cinco novas tentativas, com máximo total de 100 por entrega. Não reenvia entregas confirmadas nem cria outro ID para contornar deduplicação.

O histórico guarda número, início/fim, resultado e código HTTP, **não corpos de resposta nem texto de exceção**. Segredos e payloads não aparecem na listagem administrativa. Banco e APP_KEY devem ser preservados juntos. Não há retenção automática nem quota agregada: monitore crescimento, fila, falhas e capacidade antes de habilitar muitos destinos.

## Evidências e limites

A suíte PHP cobre assinatura, atomicidade/rollback, retries/budget, DNS/SSRF/pinning, proxy e redirect, autorização, 2FA, segredo de uso único, recuperação de lease e replay manual. Testes concorrentes incluem 20 confirmações do mesmo pagamento com um evento e 20 jobs duplicados com um POST simulado. O receptor de referência tem testes de assinatura, expiração, adulteração, deduplicação e rollback.

O Chromium exercita cadastro/desativação, chave exibida somente uma vez, resposta sem cache, bloqueio do cliente e larguras 390/768/1440. Relatório: `BROWSER-WEBHOOKS-RESULTS.json`. Transporte externo e DNS dos testes PHP são simulados; não houve entrega autenticada a um provedor real ou homologação de ambiente de produção. Isso não encerra as demais pendências da matriz.
