# Roadmap competitivo — LagosPanel

## Leitura correta da comparação

WHMCS e Paymenter são referências de produto, não um checklist de marketing. O LagosPanel já tem uma base forte de painel mobile, faturas, carteira, tickets, permissões, auditoria, fila, provisionamento por conectores e agora Efí Pix. Ainda não é correto afirmar que supera os dois em cobertura, estabilidade operacional ou ecossistema.

A vantagem buscada deve ser **operação mais clara e segura**, não apenas mais menus. Cada item abaixo precisa de contrato de produção, teste de falha e documentação antes de ser considerado pronto.

## Prioridade P0 — dinheiro, cobrança e confiança

| Lacuna | Por que pesa | Critério de conclusão |
|---|---|---|
| Estornos totais/parciais, chargeback e ledger de reversões | WHMCS e gateways maduros precisam corrigir pagamentos sem apagar histórico | Fluxos autorizados, saldo/fatura/serviço consistentes, idempotência e conciliação de divergência |
| Pagamento parcial, sobrepagamento e crédito remanescente | Evita lançamentos manuais quando o valor recebido diverge | Ledger suporta múltiplas capturas, revisão e aprovação por operador |
| Assinaturas e cobrança recorrente nativa | O checkout atual é avulso; não equivale a assinatura automática | Renovação no gateway, falhas, retry, cancelamento e sincronização de estado |
| Antifraude e risco | Cartão sem risco operacional limita escala | Regras por valor/país/velocidade, revisão manual, trilha e provedor opcional |
| Efí, Stripe e Mercado Pago em homologação real | Código simulado não comprova operação | Credenciais de teste, webhooks públicos, replay, timeout, conciliação e relatório de incidente |

## Prioridade P1 — catálogo, provisionamento e documentos

| Lacuna | Por que pesa | Critério de conclusão |
|---|---|---|
| Upgrade/downgrade com prorrata | Recurso central de billing e retenção | Cotação, aprovação, crédito/débito proporcional e atualização segura do serviço |
| Planos, bundles, addons dependentes e múltiplos ciclos | Paymenter/WHMCS são fortes no catálogo de hospedagem | Motor de composição, elegibilidade, estoque e snapshot fiscal/comercial |
| Domínios e registradores | É uma parte estrutural de um painel de hosting | Disponibilidade autoritativa, registro, transferência, renovação, EPP, DNS e expiração |
| NF-e/NFS-e, impostos e regras territoriais | PDF privado não é documento fiscal | Integração fiscal validada, cancelamento, retenção, numeração e auditoria legal |
| Provisionamento real por fornecedor | Conectores simulados não garantem criação de infraestrutura | Drivers homologados com retry seguro, reconciliação e operação manual de exceção |

## Prioridade P2 — ecossistema e escala

| Lacuna | Por que pesa | Critério de conclusão |
|---|---|---|
| Multitenancy e marcas independentes | Necessário para vender o painel a outras empresas | Isolamento de dados, domínio/marca por tenant, cobrança e permissões testados |
| Importação WHMCS/Paymenter reconciliada | Reduz custo de migração e cria uma rota comercial | Mapeamento versionado, dry-run, relatório de conflitos, senhas nunca importadas |
| API administrativa, SDK e marketplace de extensões | O ecossistema é um diferencial defensável | Versionamento, escopos, sandbox, assinatura de pacotes e compatibilidade |
| SSO/OIDC, SCIM e organizações | Atende equipes e clientes corporativos | Login, provisionamento, revogação e isolamento de sessão auditados |
| Observabilidade, SLO, alertas e backup/restore | Operação superior precisa provar disponibilidade | Métricas, traces, alertas, restore ensaiado e runbooks com RTO/RPO |

## Melhorias UX que devem continuar

O catálogo de gateways deve seguir o padrão aplicado nesta entrega: logo local, estado ativo/desativado, capacidades, ambiente, callback e confirmação de alteração no mesmo cartão. A mesma linguagem pode ser aplicada a servidores, registradores e conectores. A navegação deve priorizar status e próxima ação, com tabelas responsivas, busca, filtros e mensagens de erro acionáveis.

A assistente **Waguri Lagos**, criada pela companhia Lagos, deve continuar limitada por permissão, consentimento, fila e suporte humano. O próximo salto não é permitir que ela execute pagamentos: é oferecer explicações contextuais de faturas, status de provisionamento e artigos de ajuda sem expor segredos ou conceder autoridade implícita.

## Métricas para dizer “melhor”

Antes de comparar com WHMCS ou Paymenter, medir tempo para configurar o primeiro produto, tempo para ativar um gateway em homologação, taxa de pagamentos reconciliados automaticamente, tempo para resolver uma exceção, quantidade de cliques para encontrar um diagnóstico e percentual de operações cobertas por testes de replay/timeout. Superioridade deve ser demonstrada por esses resultados e por uma instalação reproduzível, não por quantidade de telas.
