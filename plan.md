# LagosPanel — melhoria do painel e gateways

## Objetivo

Evoluir o painel administrativo com foco em operação de cobrança, sem afirmar paridade integral com WHMCS/Paymenter, e entregar uma base segura para a integração Efí Pix.

## Decisões de produto e design

- **Movimento visual:** dashboard SaaS editorial, combinando cartões de operação com uma navegação de catálogo inspirada no Paymenter.
- **Princípios:** escaneabilidade, status visível, configuração progressiva e mobile-first.
- **Paleta:** manter o roxo Lagos como cor de ação; usar cores de marca apenas dentro dos cartões dos gateways; estados usam verde/âmbar/vermelho.
- **Layout:** catálogo de gateways em cartões responsivos, com logo, capacidades, ambiente e estado; detalhes técnicos abaixo, em vez de uma lista longa de campos sem contexto.
- **Assinaturas:** chips de capacidades, badge de ambiente, callout de segurança e QR/copia-e-cola para Pix.
- **Interação:** habilitar gateway é uma decisão explícita; campos secretos nunca voltam ao navegador; callbacks confirmam no servidor antes de quitar.
- **Tipografia:** sistema nativo já usado pelo painel; hierarquia com eyebrow, título, descrição, cartões e confirmação final.
- **Marca:** a assistente passa a se chamar **Waguri Lagos**, criada pela companhia Lagos, com o mesmo tom claro e responsável do suporte.

## Implementação

1. Atualizar a identidade textual da assistente no prompt, navegação, chat e configuração.
2. Criar catálogo visual de gateways com logos locais de Stripe, Mercado Pago e Efí Bank; agrupar campos de cada provedor e manter confirmação por senha/2FA.
3. Adicionar configuração Efí por ambiente, credenciais OAuth2, certificado mTLS, chave Pix, HMAC do webhook e expiração da cobrança.
4. Implementar cobrança Pix imediata Efí, consulta autoritativa, persistência do txid e webhook `/webhooks/efi`/`/webhooks/efi/pix` com idempotência.
5. Exibir QR Code SVG e código Pix copia-e-cola ao cliente; o retorno do navegador nunca quita a fatura.
6. Atualizar matriz de paridade com o estado real da Efí e destacar lacunas de maior impacto: recorrência, impostos/documentos fiscais, refunds/chargebacks, antifraude, registradores, extensões e importação reconciliada.

## Limites e operação

- A integração Efí exige conta Efí, client ID/secret, certificado P12/PEM, chave Pix, TLS 1.2 e mTLS no servidor do webhook; nenhum segredo será criado ou testado neste sandbox.
- Homologação será o padrão; produção só funciona quando o operador habilitar pagamentos reais.
- A tela e os testes usam `Http::fake`; isso não substitui homologação com credenciais reais.


## Segunda entrega — operação financeira

A renovação automática por saldo da carteira agora é opt-in, idempotente e auditada: uma fatura por período, um movimento de carteira e um pagamento `wallet`, com notificação de sucesso ou saldo insuficiente. A tela administrativa de faturamento mostra pagamentos, saldo reembolsável, referência interna e referência do provedor.

Estorno e chargeback passaram a exigir `billing.manage` e são registrados no ledger interno. O painel não finge que chamou o provedor externo; APIs de refund, assinaturas nativas, antifraude, documentos fiscais e registradores ainda exigem contratos, credenciais e homologação reais.
