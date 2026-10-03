# Comércio, reservas, equipe e relatórios — alpha.2

## Pedido e configuração

Carrinho é persistente por cliente, com até 50 configurações e no máximo 50 unidades por checkout. Cada configuração aceita até 10 unidades, respeitando a política do produto. Itens são normalizados, precificados no servidor e processados numa transação; um erro em qualquer item impede o pedido inteiro.

Opções são grupos de seleção única, obrigatórios ou opcionais. Um valor pode adicionar preço recorrente e instalação. Valores de outro produto, duplicados, ausentes quando obrigatórios ou desativados são recusados. Snapshot da fatura/serviço preserva nome, rótulo e valores contratados mesmo se o catálogo mudar. Ainda não há opções numéricas, texto arbitrário ou dependências condicionais.

Limite por cliente considera serviços pendentes e ativos (todos os não cancelados). Quantidade é materializada em serviços separados, não em uma instância combinada. Cada produto ainda tem um único ciclo e preço base em BRL.

## Reserva e expiração

`ORDER_RESERVATION_MINUTES` controla novos pedidos: padrão 1440 minutos, faixa de 15 a 10080. A fatura mostra prazo e timezone. O valor `stock` é **disponível**, descontadas as reservas já feitas.

Na criação do pedido, estoque é reservado. Pagamento consome a reserva. Expiração/cancelamento de pedido não pago devolve somente reservas registradas e libera o uso reservado do cupom uma única vez. Pagamento após o prazo é recusado mesmo se o cron ainda não passou. Checkouts novos no gateway são bloqueados após o prazo.

Um pagamento que chega atrasado após já ter sido capturado exige conciliação: não recriamos silenciosamente um serviço sem estoque. A manutenção marca vencimentos e libera reservas; serviços já executados não são encerrados automaticamente por esse fluxo.

Pedidos alpha.1 não receberam reservas retroativas. Cancelá-los não aumenta estoque sem evidência de reserva, evitando crédito artificial. Seus estados e valores são preservados.

## Cupons

Percentual ou valor fixo, somente primeira fatura, com limite global e por cliente. Incluem instalação. Desconto não deixa total negativo. Usos reservados contam no limite, são consumidos no pagamento e liberados no cancelamento elegível.

## Equipe

Administrador principal conserva autoridade total. Funções podem separar leitura de alteração por módulo. Não é permitido delegar permissões que o operador não possui nem retirar uma função mais poderosa. A tela não altera/rebaixa administradores principais. Funções são criadas e atribuídas/removidas; editar funções já compartilhadas não faz parte desta etapa.

Menu oculto não é o controle de segurança: cada rota administrativa verifica sua permissão. Clientes permanecem isolados nos próprios objetos. Em produção, acesso administrativo exige 2FA ativo; um operador novo deve configurá-lo no perfil antes de entrar na administração.

## Lembretes e relatórios

Lembretes são considerados após seis horas da criação da fatura, em estágios de vencimento próximo, atraso inicial e atraso de sete dias. Existe chave única por fatura/estágio. Não há disparo retroativo de todos os estágios de uma vez. Ao enviar, o job verifica novamente se a fatura está aberta.

Notificações usam explicitamente a fila do banco, inserida antes do commit financeiro. Transporte externo ainda precisa de homologação; semântica de entrega é pelo menos uma vez, não exatamente uma vez.

Relatórios separam entrada externa de pagamento interno com saldo. Recarga é recebimento, não receita/lucro reconhecido. Faturas abertas e saldos exibem posição atual, não posição histórica no fim do período. CSV filtra criação da fatura e exporta até 10 mil registros por intervalo; valores saem em centavos. Impostos, estornos e contabilidade completa permanecem pendentes.


## Limitação de requisições

As ações web têm buckets separados por finalidade. Abrir tickets não consome o limite de emissão de tokens. A API mantém um bucket compartilhado para seus três endpoints. Isso foi corrigido após reprodução em navegador e protegido por teste de regressão.
