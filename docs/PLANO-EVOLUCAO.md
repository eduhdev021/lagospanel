# Plano para competir com WHMCS e Paymenter

## Referências públicas consultadas em 03/10/2026

WHMCS descreve faturamento recorrente, provisionamento, gestão de domínios, múltiplas moedas, integrações e extensibilidade. [2](https://www.whmcs.com/what-is-whmcs/)

A página de automação também descreve suporte, impostos, faturas PDF, SSO e upgrades. [1](https://www.whmcs.com/web-hosting-automation-made-easy/)

Paymenter documenta estoque, limites por usuário, modos de quantidade, categorias e múltiplas opções de plano por produto. [2](https://paymenter.org/docs/guides/products/)

Sua lista pública de servidores inclui cPanel, Convoy, DirectAdmin, Enhance, Plesk, Proxmox, Pterodactyl, VirtFusion e Virtualizor. [5](https://paymenter.org/docs/guides/servers/)

A documentação de gateways lista Mollie, PayPal e Stripe e admite extensões adicionais. [1](https://paymenter.org/docs/guides/gateways/)

A extensão Proxmox citada pelo Paymenter é um recurso pago. Isso não impede desenvolver um adaptador próprio usando a API pública, mas não autoriza copiar código proprietário de uma extensão. [4](https://paymenter.org/docs/extensions/proxmox)

## Lotes e dependências

### A — Fundamentos e comércio (alpha.1 + alpha.2)

Autenticação, 2FA, financeiro em centavos, estoque, carrinho, configurações, papéis, API inicial, suporte, conhecimento, lembretes e relatórios. O lote implementado tem evidência local; detalhes da cobertura parcial na matriz.

### B — Financeiro avançado

Planos e intervalos por produto, moedas e preços explícitos, carteiras por moeda, impostos com snapshots, faturas/PDF, cobranças parciais, ajustes, prorrata, upgrades/downgrades, créditos e estornos reconciliados. Não transformar a carteira BRL atual em multimoeda somente trocando o símbolo: exige modelo contábil e migrations próprios.

Critério: valores exatos, nenhuma duplicidade, reversões rastreáveis, concorrência/rollback e compatibilidade com contratos reais dos gateways.

### C — Provisionamento nativo (iniciado na alpha.4)

Alpha.4 implementa o ciclo inicial cPanel/WHM API 1 com pacote, subdomínio inicial, conciliação e credenciais protegidas. Foi testado com simulação, não homologado no provedor.

Continuam: demais drivers para hospedagem, virtualização, cloud e jogos. Contratos separados para criar, consultar, suspender, reativar, alterar e encerrar; resultados assíncronos precisam de polling/webhook autenticado. Controle de capacidade, alocação, região, credenciais, SSO e reconciliação.

Critério: testes simulados **e** homologação em ambiente real isolado para cada driver/versionamento. Não habilitar por padrão nem incluir botões que fingem executar APIs inexistentes.

### D — Domínios, DNS e certificados

Catálogo TLD e regras, disponibilidade/IDN, registro, transferência, renovação, contatos, nameservers, bloqueio, EPP e DNSSEC conforme capacidade do registrador. Certificados e validação de domínio separados da cobrança.

Critério: contas de revenda, custos e políticas confirmados, operações idempotentes, vínculo da transação, falhas e devoluções testadas. Cada registrador tem capacidades e condições próprias.

### E — Operação comercial e atendimento (iniciado na alpha.3)

Alpha.3 entrega metas de resposta em horas corridas, atribuição, filtros, notas internas, anexos privados com quotas, histórico paginado e aviso ao cliente por fila. Antivírus e homologação externa ainda pendentes.

Continuam no lote: SLA avançado, departamentos com ACL, retenção, IMAP autenticado, afiliados, cotações, campanhas, downloads, incidentes, projetos e licenciamento de software. Recursos de extensões comerciais podem precisar de implementação própria ou licença válida.

### F — Migração e homologação

Exportação somente leitura, IDs mapeados, reconciliação de faturas/transações/saldos, recuperação de acesso e reenrolamento 2FA quando necessário. Ensaio de restauração e rollback. Nunca sobrescrever o banco anterior durante o desenvolvimento.

### G — Comparação mensurável

Instalações licenciadas e versões fixadas dos painéis de referência, mesmo hardware/dataset, mesmos fluxos e provedores simulados quando apropriado. Medir taxa de sucesso, p95/p99, consumo, filas, acessibilidade, ações necessárias por fluxo e recuperação de falhas. Publicar metodologia e resultados; não comparar marketing com testes locais do próprio produto.

## Quando será legítimo dizer “melhor”

- Escopo de comparação explícito, sem contar telas vazias ou integrações simuladas como homologadas.
- Fluxos comuns completos, sem perda/duplicidade de valores ou operações remotas.
- Ganhos medidos contra versões equivalentes e condições comparáveis.
- Segurança e recuperação operacional avaliadas; backup restaurado, segredos protegidos e limites documentados.
- Licenças respeitadas e processos de atualização/suporte estabelecidos.

**Esses critérios são metas de aceitação, não resultados já obtidos.** O objetivo aprovado é amplo; esta entrega não encerra os lotes B a G.
