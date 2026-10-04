# 1.6.0-dev — chat e configurações operacionais

## Conversa, em vez de formulário

O chat ocupa a área útil do painel: histórico lateral (gaveta no celular), caixa de
mensagem fixa, mensagens em sequência, Enter para enviar e Shift+Enter para quebra.
A primeira mensagem cria a conversa sem navegação. Os próximos envios e o
acompanhamento usam JSON e preservam o histórico. Renomear, copiar resposta,
excluir com confirmação e preencher novamente uma mensagem que falhou são ações
explícitas. Há formatação básica segura de texto e blocos de código; HTML do modelo
é mostrado como texto, não executado. Links de pesquisa preservam noreferrer.

A IA **não transmite tokens em streaming** nesta versão: a fila processa a chamada
e a interface consulta o estado, exibindo a resposta completa quando ela termina.
Os estados distinguem fila e processamento; não simulam texto que o modelo não gerou.
O worker database continua obrigatório. Sem ele, não haverá resposta. Trabalhos
abandonados expiram localmente após cinco minutos, sem repetir inferência incerta.

O consentimento para IA é registrado uma vez por conversa. A pesquisa web continua
opcional e exige consulta e consentimento próprios a cada solicitação. Não é
necessário marcar novamente a autorização da conversa em toda mensagem. A IA não
recebe dados da conta automaticamente nem ganha permissão para executar ações.

O texto, respostas e títulos personalizados continuam criptografados no banco.
Todas as rotas conferem titularidade. Os limites de mensagens/conversas e o
controle de requisições permanecem. IDs idempotentes protegem contra envio duplicado,
inclusive na criação com a primeira mensagem; falhas de rede preservam o rascunho.

## Cinco novas seções funcionais

Todas ficam na central Configurações. As opções operacionais são restritas ao
administrador principal e exigem senha atual, TOTP pessoal quando ativo, ciência
do efeito e controle de versão para evitar sobrescrita concorrente.

| Seção | Opções efetivamente conectadas ao sistema |
|---|---|
| Faturamento e documentos | Emissor e contato do PDF; reserva de pedidos em minutos; prazo de vencimento de novas recargas |
| Automação de cobrança | Ligar/desligar renovação; antecedência em dias; suspensão por atraso e prazo; lembretes e antecedência |
| Gateways de pagamento | Stripe, segredo do webhook, Mercado Pago, habilitação individual e ambiente de pagamentos reais |
| Atendimento e SLA | Prazo por prioridade; cota de anexos por cliente em MiB |
| Recursos e limites | Provisionamento nativo; entregas de webhooks; solicitações de IA por cliente/dia UTC |

As regras são consumidas pelos mesmos serviços de cobrança, pagamento, PDF,
atendimento e fila existentes. Não são campos decorativos. A central também
passou a reunir respostas prontas, base de conhecimento e avisos/status.

Configuração é criptografada em `operational_settings`; não reescreve `.env`.
O valor salvo substitui sua opção de ambiente. Segredos nunca são preenchidos no
HTML, nos logs de auditoria ou no retorno de validação. Vazio mantém uma chave;
a remoção exige ação explícita e o gateway deve ficar desativado se faltar chave.
A seleção de campos é fixa; não é possível definir chaves arbitrárias de config.

## Cuidados operacionais

- Cron deve chamar o scheduler Laravel a cada minuto; `lagos:maintenance` continua
  agendado a cada hora. A tela não instala cron ou systemd.
- Reduzir o prazo de suspensão pode atingir faturas vencidas na próxima execução.
  Desabilitar não desfaz operações já enfileiradas nem restaura serviços.
- Faturas emitidas e reservas existentes não são reescritas. Configurar SLA afeta
  novos tickets e alterações explícitas de prioridade, não muda todo o histórico.
- Pagamentos reais e credenciais precisam corresponder ao mesmo ambiente. Não
  mude o ambiente durante uma conciliação; valide seus provedores com testes reais.
- Web/CLI/worker carregam os valores pelo SiteConfiguration; o worker atualiza
  configuração antes de cada job e restaura valores de base se um override sumir.
- Nenhum pagamento/provisionamento real foi executado para estes testes.

## Cobertura vs. WHMCS

A página `Configurações → Consultar recursos disponíveis e limitações` apresenta
os recursos reais e lacunas, sem botões de módulos inexistentes. **Não há paridade
completa com WHMCS.** Continuam pendentes motores completos de domínios/registradores,
afiliação, impostos, múltiplas moedas, campos personalizados, departamentos
customizados, email piping/escalonamentos e editor de todos os templates de e-mail.
Os departamentos atuais são Suporte e Financeiro; os PDFs não são documentos fiscais.

A comparação de organização considerou a documentação de automação, campos e
suporte do WHMCS: [1](https://docs.whmcs.com/about-whmcs/whmcs-glossary/) e
[2](https://help.whmcs.com/m/setup/l/1112140-configuring-support-departments).
Não foram copiados código ou assets do WHMCS.

## Consulta de atualização

A mensagem genérica do atualizador agora distingue falha de leitura do Git pelo
processo web de falha de consulta/CI do GitHub. Isso melhora o diagnóstico; não
corrige automaticamente permissões, proc_open, DNS, TLS, rate limit ou worker.

## Testes e reprodução

- PHPUnit: novas rotas JSON, idempotência, consentimento, titularidade, criptografia,
  expiração de trabalho pendente, configurações e aplicação em serviços reais locais.
- Playwright: `tests/browser_chat_operations.py`; usa a fixture local de experiência,
  `tests/chat-fixture.php` e resposta Ollama simulada. Testa worker, envio sem reload,
  falha/rascunho, layout, histórico, edição no ADM e XSS (literal na captura móvel).
- Com o ambiente local descrito em LOGIN-SOCIAL-E-NOVA-INTERFACE.md, inicie a fixture
  `LAGOS_TEST_MODE=1 php tests/chat-fixture.php prepare` e execute o teste de navegador.
  Não execute fixtures no servidor real; elas exigem banco `.cache/experience.sqlite`.
- Migration nova: `2026_10_03_000019_chat_and_operational_settings.php`.
  Faça backup, instale dependências e aplique migrations/caches antes de liberar o site.
