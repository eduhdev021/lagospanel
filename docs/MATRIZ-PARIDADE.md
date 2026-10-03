# Matriz de evolução — WHMCS / Paymenter como referências

**Escopo solicitado: todas as frentes de operação. Estado: base 1.3.0 + desenvolvimento 1.4.0-dev na main.**

Esta é uma matriz de trabalho, não uma declaração de que o LagosPanel já possui tudo, nem um inventário exaustivo de marketplaces. Linhas incluem metas complementares propostas; não afirmam que cada concorrente oferece cada recurso nativamente. “Implementado localmente” significa o recorte descrito e seus testes, não aprovação de produção. “Homologação pendente” significa código/contrato presente, sem operação real comprovada.

Referências oficiais e critérios de comparação: `PLANO-EVOLUCAO.md`. Evidência local: `PHPUNIT-RESULTS.txt`, `BROWSER-RESULTS.json`, `BROWSER-EXPANSION-RESULTS.json`, `BROWSER-SUPPORT-RESULTS.json`, `BROWSER-NATIVE-RESULTS.json`, `BROWSER-AAPANEL-RESULTS.json`, `BROWSER-DOCUMENTS-RESULTS.json`, `BROWSER-PTERO-AI-RESULTS.json` e `CONCURRENCY-RESULTS.json`. Nenhuma contagem nesta matriz prova superioridade.

## Contas e segurança

| Recurso-alvo | Estado | Evidência / limite |
|---|---|---|
| Cadastro, confirmação e recuperação | Implementado localmente | Testes de domínio e Chromium; e-mail externo pendente |
| 2FA e códigos de recuperação | Implementado localmente | Replay, limite de tentativas, expiração e uso único testados |
| Papéis e permissões de equipe | Implementado localmente | Leitura/alteração por módulo; sem delegar autoridade superior |
| 2FA obrigatório para equipe em produção | Implementado localmente | Middleware e teste de exigência; operação externa pendente |
| Contatos, subcontas e organizações | Planejado | Não existe modelo de organizações/subcontas |
| Campos personalizados e dados fiscais | Planejado | Não há cadastro fiscal completo |
| Antifraude e avaliação de risco | Planejado | Não há MaxMind/FraudLabs ou motor próprio |
| SSO/OAuth/OpenID e login social | Planejado | Login social/OAuth/OpenID ausentes; SSO cPanel e Plesk possuem recortes próprios documentados |
| LGPD: exportação, retenção e anonimização | Planejado | Políticas legais precisam de validação específica |
| Multitenancy e marcas independentes | Planejado | Não confundir papéis com isolamento entre empresas |

## Catálogo e pedidos

| Recurso-alvo | Estado | Evidência / limite |
|---|---|---|
| Catálogo e edição de produtos | Implementado localmente | Preço e disponibilidade validados no servidor |
| Carrinho multiproduto persistente | Implementado localmente | Uma fatura; snapshots; reenvios não duplicam nem apagam carrinho novo |
| Opções configuráveis com preço | Parcial | Seleções e cobrança prontas; mapeamento de recursos depende do driver |
| Estoque e reservas com expiração | Implementado localmente | Reservas novas; concorrência sem overselling e devolução única |
| Limite por cliente e quantidade | Implementado localmente | Serviços pendentes/ativos contam no limite |
| Quantidade separada por serviço | Implementado localmente | Uma instância de serviço por unidade |
| Quantidade combinada em um serviço | Planejado | Ainda não implementada |
| Múltiplos planos/preços por produto | Planejado | Hoje cada produto tem um ciclo |
| Ciclos de cobrança adicionais | Implementado localmente | Diário a trienal e pagamento único; regras de calendário testadas |
| Categorias, imagens e ordenação comercial | Parcial | Descrição/slug ativos; taxonomia e imagens por produto faltam |
| Bundles, addons e produtos dependentes | Planejado | Sem motor de composição |
| Cotações e aprovação comercial | Implementado localmente | Proposta avulsa em BRL, aceite/recusa, validade, revisão e fatura única; execução manual sem estoque/provisionamento |
| Cupons fixos e percentuais | Implementado localmente | Primeira fatura; limites global e por cliente |
| Cupons recorrentes e elegibilidade avançada | Planejado | Sem recorrência, segmentação ou regras combinadas |
| Upgrades/downgrades e prorrata | Planejado | Não há alteração proporcional automática |
| Configurações remotas após upgrade | Planejado | Depende dos drivers e da reconciliação |

## Financeiro

| Recurso-alvo | Estado | Evidência / limite |
|---|---|---|
| Faturas e registros de pagamento | Implementado localmente | BRL, quitação integral e idempotência |
| Carteira, recargas e pagamentos internos | Implementado localmente | Uma moeda; saldos e concorrência testados |
| Renovações e lembretes | Implementado localmente | Fila deduplicada e verificação antes de enviar |
| Conciliação de recebimentos excepcionais | Parcial | Registro e conclusão manual; sem estorno automático |
| Cancelamento de pedido não pago | Implementado localmente | Libera reservas registradas; bloqueia pagamento vencido |
| Relatórios e exportação CSV | Parcial | Indicadores básicos; não são lucro nem contabilidade fiscal |
| Faturas PDF e layout fiscal | Parcial | PDF não fiscal privado, itens/valores do snapshot e cadastro atual; sem emissão fiscal ou assinatura |
| Múltiplas moedas e câmbio | Planejado | Precisa de preços/carteiras por moeda e snapshots de câmbio |
| Impostos, VAT e regras territoriais | Planejado | Não implementado |
| Documentos fiscais brasileiros | Planejado | Integração fiscal e validação legal necessárias |
| Pagamentos parciais e sobrepagamentos | Planejado | Capturas incompatíveis vão para conciliação |
| Estornos totais/parciais e chargebacks | Planejado | Sem API de reembolso e ledger de reversões |
| Débito recorrente e assinaturas no gateway | Planejado | Checkout atual não equivale a assinatura automática |
| Cobrança por uso, medição e excedentes | Planejado | Sem coletor de uso tarifável |
| Juros, multas e tolerâncias por produto | Planejado | Políticas avançadas ausentes |
| Afiliados e comissões | Planejado | Sem motor de comissão/pagamento |

## Gateways

| Recurso-alvo | Estado | Evidência / limite |
|---|---|---|
| Stripe | Homologação pendente | Checkout e webhook implementados; contrato simulado |
| Mercado Pago | Homologação pendente | Preferência e consulta autenticada; contrato simulado |
| Confirmação manual | Implementado localmente | Conferência por operador autorizado e registro auditado |
| PayPal | Planejado | Adaptador independente ainda não portado |
| Mollie | Planejado | Adaptador independente ainda não implementado |
| Pix direto e boletos | Planejado | Capacidade depende de PSP/banco e homologação |
| Asaas, Efí, PagBank e Pagar.me | Planejado | Integrações próprias a especificar e homologar |
| Adquirentes internacionais adicionais | Planejado | Inventário depende dos países/contratos |
| Criptoativos | Planejado | Risco, câmbio e conformidade precisam ser definidos |

## Provisionamento

| Recurso-alvo | Estado | Evidência / limite |
|---|---|---|
| Serviços manuais | Implementado localmente | Pagamento não finge criar infraestrutura |
| Conector HTTPS/JSON próprio | Homologação pendente | Contrato simulado; não é adaptador nativo |
| Fila e reconciliação de resultado incerto | Parcial | Sem repetir criação cegamente; resolução operacional ainda manual |
| cPanel/WHM | Parcial / homologação pendente | Ciclo WHM API 1, snapshots, conciliação e SSO temporário com senha/2FA; faltam WHM real, alteração de pacote e domínio próprio no checkout |
| aaPanel | Parcial / homologação pendente | Sites gerenciados via API clássica; sem contas isoladas, FTP/banco/quotas/SSL/DNS automáticos; somente simulação |
| Plesk | Parcial / homologação pendente | XML API, ciclo, conciliação e FTP/FTPS; desenvolvimento da main adiciona clientes isolados após pagamento e SSO do titular; upgrades e gestão avançada pendentes |
| DirectAdmin | Parcial / homologação pendente | Contas por pacote, ciclo, conciliação e acesso inicial privado; faltam SSO, upgrades e gestão avançada |
| Enhance | Planejado | Driver nativo ausente |
| Pterodactyl e jogos | Parcial / homologação pendente | Ciclo de servidores, planos, criação de contas pelo ADM ou após pagamento, energia via Client API do cliente; faltam console/SSO, backups, reinstalação, upgrades e validação real |
| Proxmox | Planejado | Implementação própria via API ou licença válida para reutilização |
| Virtualizor | Planejado | Driver nativo ausente |
| VirtFusion | Planejado | Driver nativo ausente |
| Convoy | Planejado | Driver nativo ausente |
| SolusVM | Planejado | Driver nativo ausente |
| Cloud: AWS, DigitalOcean e Hetzner | Planejado | Contas, custos, quotas e drivers a homologar |
| Capacidade, localização e IPAM | Planejado | Sem alocação automática completa |
| Backups, reinstalação e console | Planejado | Depende das capacidades e permissões dos provedores |
| Licenciamento de software | Planejado | Sem servidor de licenças/revogações |

## Domínios e certificados

| Recurso-alvo | Estado | Evidência / limite |
|---|---|---|
| Catálogo TLD e preços por operação | Planejado | Sem registro/transferência/renovação nativos |
| Busca de disponibilidade e IDN | Planejado | Disponibilidade não pode ser inferida de consulta DNS |
| Registro, transferência e renovação | Planejado | Exige registrador e conta de revenda |
| Enom e OpenSRS | Planejado | Drivers e credenciais de teste necessários |
| ResellerClub e Namecheap | Planejado | Drivers e credenciais de teste necessários |
| Registro.br e outros registradores | Planejado | Validar modalidade de integração e condições disponíveis |
| Contatos, EPP, locks e nameservers | Planejado | Capacidades específicas por registrador |
| Zona DNS e DNSSEC | Planejado | Separar autoridade DNS da titularidade do domínio |
| Expiração, resgate e sincronização | Planejado | Regras/custos precisam de confirmação autoritativa |
| SSL e validação de domínio | Planejado | Não há emissão/renovação de certificados |

## Atendimento e conteúdo

| Recurso-alvo | Estado | Evidência / limite |
|---|---|---|
| Tickets e respostas | Implementado localmente | Cliente/equipe, notas internas, histórico paginado e fechamento/reabertura; isolamento e escape testados |
| Respostas prontas | Implementado localmente | Biblioteca da equipe, edição versionada e inserção em rascunho sem envio automático; texto simples |
| Departamentos avançados e equipes | Parcial | Categorias técnicas/financeiras simples; sem ACL por departamento |
| SLA, atribuição e prioridades | Parcial | Metas em horas corridas, prioridades, responsáveis e filtros; faltam calendário comercial, escalonamento e relatório histórico |
| Anexos e antivírus | Parcial | Anexos criptografados, quotas por chamado/conta, autorização e integridade; sem antivírus/CDR/retencão automatizada |
| IMAP, piping e respostas por e-mail | Planejado | Remetente sozinho não autentica o cliente |
| Base de conhecimento | Implementado localmente | Publicação, rascunho, categoria e busca; texto simples |
| Downloads e licenças de acesso | Parcial | Arquivos privados criptografados, hash e acesso por serviço ativo; sem servidor de licenças/antivírus |
| Anúncios, incidentes e status page | Parcial | Publicação agendada, histórico e estados de incidentes/manutenções; informação manual sem probes/medição de uptime |
| Chat e canais adicionais | Parcial / homologação pendente | Chat IA Ollama com chave/modelo, pesquisa web mediada com fontes e consentimento; sem chat humano ao vivo ou homologação real |
| Projetos e tarefas faturáveis | Planejado | Sem gestão de projetos |

## Plataforma e operação

| Recurso-alvo | Estado | Evidência / limite |
|---|---|---|
| API pública com tokens e escopos | Parcial | Leitura de serviços/faturas e criação de ticket, somente do dono |
| API administrativa e SDK | Planejado | Sem compatibilidade com APIs de outros painéis |
| Webhooks de saída e event bus público | Planejado | Sem assinaturas/retries de eventos para terceiros |
| SDK de extensões e marketplace | Planejado | Contrato JSON não é SDK completo |
| Auditoria de ações | Parcial | Eventos de aplicação; não é ledger inviolável |
| Observabilidade, métricas e alertas | Planejado | Logs/diagnóstico básicos não substituem monitoramento |
| Fila e cron | Implementado localmente | Banco transacional; supervisor externo necessário |
| Backups e restauração | Planejado | Procedimentos descritos; restore operacional não homologado |
| Alta disponibilidade e failover | Planejado | Sem evidência em infraestrutura distribuída |
| Importação da instalação anterior | Planejado | Campos preparados não constituem importador |
| Importação WHMCS/Paymenter | Planejado | Mapeamento e conciliação precisam ser construídos |
| Localização e acessibilidade completas | Parcial | pt-BR/BRL e mobile; sem auditoria WCAG integral |
| CI automatizado | Parcial | Workflow ativo; execução no GitHub aprovada na 1.0.0, acompanhar cada commit posterior |
| Benchmark comparativo reproduzível | Planejado | Sem prova de superioridade |

**104 linhas de escopo acompanhadas.** Novas integrações e versões exigirão revisar esta matriz; linhas planejadas não são funcionalidades instaladas.
