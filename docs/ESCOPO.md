# Escopo real — 1.2.0

## Direção aprovada

Abranger as operações de WHMCS e Paymenter: hospedagem, domínios, cloud/VPS, jogos, financeiro, atendimento e extensões. O alvo é funcionalidade equivalente e melhorias mensuráveis, preservando a identidade visual do LagosPanel e a arquitetura independente.

**Este alvo ainda não foi alcançado.** O ecossistema de extensões é aberto, algumas soluções são comerciais e o inventário precisa ser versionado. Não há declaração de paridade total nem de superioridade comprovada. `MATRIZ-PARIDADE.md` e `MATRIZ-PARIDADE.csv` distinguem implementado, parcial e planejado.

## Entregue até 1.2.0

- Instalador web com chave temporária, banco vazio e bloqueio; configuração administrativa de nome/URL/logo/contato/cadastro/SMTP. Não substitui preparação do servidor, DNS/TLS, worker/cron ou importação de dados.
- Pesquisa web Ollama mediada pelo painel, independente de tool calling, com consulta pública, fontes e consentimento; sem navegação autônoma ou validação automática de veracidade.

- Pterodactyl: ciclo de servidores, recursos por plano e consultas de instalação; criação de usuário remoto pelo ADM e vínculo conferido automaticamente, além de vínculo manual. Criação após pagamento quando habilitada no produto e energia com Client API do cliente. Ainda sem console/SSO, backups, reinstalação, upgrades ou homologação real.
- Ollama: chave/modelo no ADM, descoberta de catálogo, chat privado com consentimento e fila. Sem ações administrativas ou leitura automática de conta; catálogo público testado de verdade; inferência e pesquisa autenticadas continuam simuladas.
- PDF de cobrança privado e não fiscal, e biblioteca de respostas prontas com inserção manual no rascunho. Validados em PHP e navegador; detalhes em `DOCUMENTOS-E-MODELOS.md`.
- aaPanel nativo: ciclo de sites gerenciados, conciliação e exclusão preservando arquivos; simulação local, sem contas isoladas ou homologação real. Consulte `AAPANEL.md`.
- cPanel/WHM nativo: ciclo de conta, snapshots, consulta/conciliação, proteção contra duplicações locais e acesso inicial protegido. Validado com simulação; SSO temporário implementado com senha/2FA e validação da origem. Sem WHM real ou alteração de pacote.
- Atendimento: prioridades, responsáveis, filtros, metas em horas corridas, notas internas, anexos privados criptografados, fechamento/reabertura, histórico paginado e aviso por fila ao cliente após resposta pública.
- Carrinho persistente multiproduto, checkout transacional e repetição idempotente sem apagar um carrinho novo.
- Grupos de opções selecionáveis, preços de instalação/recorrência e snapshot do que foi contratado.
- Limite por cliente e controle de múltiplas unidades.
- Cupons percentuais e fixos, limite global e por cliente.
- Reservas de estoque com prazo; expiração/cancelamento de pedidos não pagos, devolvendo estoque e uso de cupom uma única vez. Não se aplica retroativamente a reservas antigas sem registro.
- Papéis de equipe com permissões de leitura/alteração, proteção contra delegação superior à própria autoridade, menus filtrados e 2FA exigido para a administração em produção.
- API v1 do cliente, tokens com escopos, expiração, hash, revogação e invalidação após mudanças de senha/2FA.
- Base de conhecimento: rascunhos, publicação, busca e texto escapado.
- Relatórios de recebimentos, pagamentos internos, saldos e faturas; exportação CSV. Pagamento com saldo é separado da entrada externa para não duplicar os indicadores.
- Lembretes financeiros deduplicados, cancelados no envio se a fatura já não estiver aberta.
- Registro de pagamentos autenticados que não puderam ser aplicados e fila administrativa de conciliação manual.
- Ciclos diário, semanal, mensal, trimestral, semestral, anual, bienal, trienal e pagamento único; um ciclo por produto nesta etapa.
- Workflow de CI ativo e execução remota aprovada na 1.0.0; consultar Actions para o estado de cada commit posterior.

## Limitações importantes que continuam

1. **Dados anteriores não foram importados.** Houve migrations de esquema sobre a demonstração independente, não migração do núcleo anterior nem importação WHMCS/Paymenter.
2. Os adaptadores cPanel/WHM, aaPanel e Pterodactyl existem em recortes iniciais, com homologação real pendente. Ainda não há adaptadores nativos de Plesk, DirectAdmin, Proxmox, Virtualizor, VirtFusion, registradores ou provedores cloud. O conector HTTPS/JSON próprio não equivale a esses adaptadores.
3. Opções configuráveis calculam preços e são enviadas ao conector JSON. No cPanel, o pacote WHM define os recursos; contratações com opções selecionadas são bloqueadas para não cobrar recursos não mapeados. Domínio inicial é subdomínio gerado sob domínio-base do operador, não registro de domínio próprio do cliente.
4. Financeiro continua **BRL, quitação integral e carteira única**. Multimoeda, impostos, documentos fiscais, prorrata, planos alternativos por produto, pagamentos parciais, estornos automáticos e reconciliação bancária não estão prontos.
5. Encerrar uma análise de recebimento é uma anotação auditada; não devolve dinheiro, não altera o saldo nem quita a fatura. O webhook continua com erro quando não consegue aplicar o pagamento, permitindo reenvios do provedor.
6. Solicitar cancelamento de serviço ativo não apaga recursos nem estorna valores. Cancelar um **pedido ainda não pago** é outro fluxo, que pode devolver apenas reservas registradas.
7. Equipe permite criar funções e atribuir/remover acesso; ainda não há times por departamento, isolamento multitenant nem escopos por carteira de clientes.
8. API é do cliente, não uma API administrativa completa. Não é compatível com as APIs de WHMCS/Paymenter e não implementa webhooks de saída.
9. Suporte tem anexos e metas básicas de resposta, mas não antivírus, retenção/exclusão automatizada, escalonamento, calendário comercial, relatório de cumprimento de SLA, ACL por departamento, chat humano ao vivo, respostas por IMAP, base multilíngue ou disparo de marketing.
10. Gateways continuam desativados por padrão, testados com respostas simuladas. SMTP de demonstração grava mensagens em arquivo; não há comprovação de envio externo.
11. Não houve homologação desta versão em MariaDB/MySQL, implantação VPS/TLS, restore completo, carga sustentada, failover ou auditoria externa de segurança.
12. O CSS e os assets originais foram mantidos. Novas telas seguem esse sistema visual, mas não há comprovação de equivalência pixel a pixel ou conformidade integral WCAG.
