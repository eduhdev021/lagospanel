# Integrações e limites de homologação

## Stripe

Configurar `STRIPE_ENABLED=true`, `STRIPE_SECRET` e `STRIPE_WEBHOOK_SECRET`. O servidor cria uma Checkout Session com valor, BRL e identificação da fatura retirados do banco. O retorno do navegador **não** quita a fatura.

Webhook: `POST /webhooks/stripe`. Assinatura HMAC sobre corpo original, janela de 300 segundos; aceita `checkout.session.completed` e `checkout.session.async_payment_succeeded` somente quando `payment_status=paid`. Valor integral, moeda, ambiente e referência são validados. Eventos repetidos não duplicam a quitação. A chave secreta precisa ter acesso à mesma conta que assina o webhook.

## Mercado Pago

Configurar `MP_ENABLED=true` e `MP_ACCESS_TOKEN`. Gera preferência com referência `LAGOS-{id}` e URL de notificação. Webhook: `POST /webhooks/mercadopago`, campo `data.id`. O conteúdo postado não é prova de pagamento: a aplicação consulta `/v1/payments/{id}` autenticada no provedor e verifica aprovação, ambiente, referência, moeda e valor. Endpoint público limitado por taxa; homologar também eventos/reenvios e proteção adicional no proxy. Não implementa assinatura de notificação MP; a autoridade é a consulta autenticada à API.

`PAYMENTS_LIVE=false` por padrão. Em `APP_ENV=production`, gateways habilitados rejeitam chamadas enquanto `PAYMENTS_LIVE` não for verdadeiro. Isso não substitui testar credenciais, contas comerciais, moeda, HTTPS e webhooks reais.

## Conector próprio de serviços

Não é adaptador nativo de um painel comercial. É um contrato para um endpoint controlado/homologado pelo operador:

- URL HTTPS base configurada por administrador; rotas POST `/create`, `/suspend`, `/unsuspend`, `/terminate`.
- `Authorization: Bearer ...`, `Idempotency-Key: ...`.
- JSON: `service_id`, `external_id`, `remote_id`, `name`, `customer_email`, `configuration` (snapshot de opções, vazio em serviços sem configuração). Este campo foi acrescentado na alpha.2; ajuste provedores com schema estrito antes de atualizar.
- Resposta de sucesso: `{"success":true,"remote_id":"id-do-recurso"}`. Criação exige ID remoto não vazio. HTTP 200 com erro, timeout, redirecionamento ou JSON sem confirmação não ativa o serviço.
- O destino recebe dados do cliente. Cadastre apenas endpoints confiáveis e restrinja o tráfego de saída por firewall/allowlist. O aplicativo exige HTTPS e não segue redirects, mas não implementa uma allowlist de rede contra administradores maliciosos.
- Credencial armazenada com criptografia vinculada a `APP_KEY`; não aparece na serialização do modelo.
- A operação e o job são persistidos no mesmo banco/transação. Falhas incertas passam a `review`; criações não são repetidas automaticamente.
- Uma quitação recebida durante suspensão por atraso agenda reativação compensatória. Confirmação assíncrona do provedor não é suportada: `success=true` precisa significar operação concluída, não apenas aceita.

Os testes usam `Http::fake`, incluindo timeout, erro com HTTP 200, replay e pagamento durante suspensão. Nenhum recurso real foi criado, suspenso ou apagado nesta entrega.


## Recebimentos que exigem análise

Quando uma captura autenticada chega à camada de quitação mas não pode ser aplicada (valor incorreto, reserva expirada, fatura inexistente etc.), ela é registrada em `payment_reviews`, deduplicada por gateway/referência. Eventos sem assinatura válida ou ainda sem confirmação autoritativa não alimentam essa fila.

A administração permite registrar uma conclusão manual com permissão financeira de alteração. Isso não é uma chamada de reembolso, não credita carteira e não marca fatura como paga. O erro do webhook não é convertido artificialmente em sucesso. Homologue políticas de reenvio, alertas e conciliação antes de aceitar dinheiro real.


## cPanel/WHM nativo — alpha.4

Driver separado do conector JSON, usando WHM API 1. O fluxo de criação/consulta/suspensão/reativação/encerramento e a conciliação estão implementados e testados com transporte simulado. Não houve acesso a um WHM real. Configuração, permissões, limites e roteiro de homologação: `CPANEL.md`.

Operações agora têm identificação de execução: uma conclusão atrasada não pode sobrescrever outra execução local. Operações conflitantes não são silenciosamente tratadas como iguais. Isso não torna chamadas remotas exatamente-uma-vez; o WHM não recebe nossa chave de idempotência do protocolo JSON. Não há retry automático.

## aaPanel nativo — alpha.5

API clássica para sites gerenciados: criação/consulta/suspensão/reativação/remoção da configuração, mantendo arquivos. Não cria contas de hospedagem isoladas, FTP, bancos ou acesso administrativo do cliente. Mesmo bloqueio global e conciliação das operações nativas; assinatura própria aaPanel, não Bearer do conector JSON. Testes com provedor simulado. Configuração, segurança e homologação: `AAPANEL.md`.

## Pterodactyl e Ollama — alpha.7

Pterodactyl é driver nativo da Application API, não conector JSON próprio: recursos, ciclo do servidor, identidade externa e leitura/conciliação. Requer vincular previamente a conta remota do cliente. Veja `PTERODACTYL.md`.

Ollama é integração de suporte, independente do provisionamento. Em Integrações → Configurar chat Ollama e modelos, salve origem/chave, consulte catálogo, escolha modelo e habilite. Não usa chave no navegador e não oferece ferramentas ao modelo. Veja `OLLAMA.md`.

Ambos foram validados com respostas simuladas; não houve contato com provedores reais nesta rodada.
