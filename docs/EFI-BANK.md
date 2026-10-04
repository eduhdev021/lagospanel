# Efí Bank — integração Pix no LagosPanel

## Estado

A integração adiciona cobrança Pix imediata via Efí, persistência do `txid`, QR Code/copia-e-cola, consulta autoritativa e callback idempotente. O padrão é **homologação**. Nenhuma credencial real foi usada nesta implementação.

## Requisitos da Efí

- Conta Digital Efí e uma aplicação com `Client_Id`/`Client_Secret` para o ambiente escolhido.
- Certificado P12/PFX ou PEM, enviado pela Efí e usado também na autorização OAuth2.
- Escopos mínimos para esta integração: `cob.write`, `cob.read` e `webhook.write`/`webhook.read` conforme a operação do operador.
- Produção: `https://pix.api.efipay.com.br`.
- Homologação: `https://pix-h.api.efipay.com.br`.
- Webhook em HTTPS com TLS 1.2, mTLS usando a cadeia pública da Efí e uma camada adicional HMAC na URL.
- O painel consulta `GET /v2/cob/{txid}` antes de registrar o pagamento; o payload do callback não é tratado como prova suficiente.

## Fluxo implementado

1. Cliente escolhe **Pix via Efí Bank** na fatura.
2. O painel autentica por OAuth2 com certificado mTLS e cria `PUT /v2/cob/{txid}`.
3. O `txid` e o `pixCopiaECola` ficam na tabela `gateway_charges`; reaberturas reutilizam a cobrança ativa.
4. A tela local exibe QR Code SVG gerado no navegador e o código copia-e-cola.
5. A Efí chama `/webhooks/efi` ou `/webhooks/efi/pix` com HMAC na query.
6. O painel consulta a cobrança, confere `CONCLUIDA`, valor e `endToEndId`, e só então usa o ledger existente para quitar a fatura.
7. Reenvios do mesmo callback são deduplicados pelo pagamento `gateway=efi` + `reference=endToEndId`.

## Configuração

Os campos podem ser salvos em **Administração → Configurações → Gateways de pagamento** ou definidos no `.env`:

- `EFI_ENABLED`
- `EFI_ENVIRONMENT=homologacao|producao`
- `EFI_CLIENT_ID`
- `EFI_CLIENT_SECRET`
- `EFI_CERTIFICATE_PATH`
- `EFI_CERTIFICATE_PASSWORD`
- `EFI_CERTIFICATE_TYPE=PEM|P12`
- `EFI_PIX_KEY`
- `EFI_WEBHOOK_HMAC`
- `EFI_CHARGE_EXPIRATION`

URL sugerida para o cadastro da Efí: `https://seu-dominio/webhooks/efi?hmac=SEU_HMAC&ignorar=`. O parâmetro `ignorar=` evita que a Efí acrescente `/pix`; a aplicação também oferece a rota `/webhooks/efi/pix` caso o provedor acrescente o sufixo.

## Fontes oficiais consultadas

- Efí — credenciais, certificado e autorização: https://dev.efipay.com.br/docs/api-pix/credenciais/
- Efí — cobranças imediatas: https://dev.efipay.com.br/docs/api-pix/cobrancas-imediatas/
- Efí — webhooks e mTLS: https://dev.efipay.com.br/docs/api-pix/webhooks/
- Paymenter — gateways: https://paymenter.org/docs/guides/gateways/
- WHMCS — payment gateways: https://docs.whmcs.com/9-0/payments/payment-gateways/

## Limite de homologação

A suíte automatizada usa `Http::fake`. Ela valida contrato, idempotência, ambiente e não exposição de segredos, mas não substitui teste real com certificado, conta Efí, webhook público, TLS/mTLS, fila e monitoramento.
