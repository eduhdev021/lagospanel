# Orçamentos, avisos/status e downloads

Código na main de desenvolvimento **1.4.0-dev**, com o tema e assets existentes. Não há nova release estável nem alegação de paridade integral com outros painéis.

## Orçamentos

ADM → Orçamentos. Usa `billing.view` para consulta e `billing.manage` para alterações, a mesma separação do faturamento. Informe ID do cliente, título, termos, validade, prazo de pagamento e itens JSON:

```json
[{"name":"Configuração avulsa","quantity":2,"unit_minor":15000}]
```

`unit_minor` é o preço unitário em **centavos BRL**. Há no máximo 50 itens, quantidade de 1 a 1.000, unidade positiva até R$ 1.000.000,00 e total até R$ 10.000.000,00. O servidor recalcula o total e ignora totais do navegador.

Fluxo: rascunho editável → disponibilizar → cliente aceita ou recusa. Validade inclui o dia indicado no timezone da aplicação. Rascunho não enviado, inclusive se retirado, não aparece ao cliente. Conteúdo/preços não podem mudar após disponibilização: para renegociar, retire a proposta e crie outra. O cliente deve estar autenticado com e-mail verificado, ser o titular e confirmar a leitura das condições.

O aceite cria **uma única fatura avulsa**, com snapshot dos itens, prazo contado a partir do aceite, notificação na fila e auditoria. Repetições retornam a mesma fatura. Vários aceites ou aceite contra retirada são serializados com locks e revisão; não geram faturas órfãs. Essa fatura utiliza carteira, gateways e PDF não fiscal já existentes. O pagamento continua sujeito aos controles existentes, sem qualquer homologação de gateway real nesta alteração.

**Limites:** cobrança e entrega avulsas/manuais, sem reserva de catálogo/estoque, criação de serviço, imposto, assinatura digital, PDF próprio da proposta ou automação remota. Aceitar não paga a fatura. Retirar orçamento não é cancelar/reembolsar fatura aceita; operações financeiras não implementadas não são simuladas.

## Avisos, incidentes e manutenções

ADM → Avisos e status, permissões novas `bulletins.view/manage`. A página pública é `/avisos`, sem autenticação; publique somente conteúdo público, nunca segredos ou dados pessoais. Publicações podem ser rascunhos ou agendadas para data/hora da aplicação. Tipo: aviso, incidente ou manutenção. Impacto: informativo, degradação ou indisponibilidade; avisos são sempre informativos.

O histórico é append-only pela interface. Cada atualização informa estado aberto/em observação/resolvido e texto. Revisão impede sobrescrita por formulário antigo. Desmarcar publicação retira todo o histórico do acesso público sem apagá-lo. Não há HTML executável: conteúdo é escapado como texto. Contagem pública considera somente incidentes/manutenções publicados, com impacto e não resolvidos.

**Limites:** registro manual da equipe, sem probes, coleta de uptime, feed, inscrição de notificações ou promessa de disponibilidade. “Nenhum incidente com impacto informado” não significa que todos os servidores foram verificados.

## Downloads

ADM → Downloads, permissões novas `downloads.view/manage`. Arquivos gerais exigem cliente autenticado/verificado. Arquivos com `product_id` exigem serviço **do próprio cliente, do produto exato, em estado ativo**. Serviço pendente/suspenso/cancelado não concede acesso. A equipe com permissão de leitura pode conferir arquivos mesmo desativados; o cliente não pode. Desativar revoga o acesso de cliente sem apagar o arquivo/histórico.

Até 10 MiB por arquivo, extensões PDF/ZIP/TXT/GZ/TAR; vazio é rejeitado. Conteúdo não é extraído nem executado. Nome entregue é gerado, sem encaminhar nome arbitrário do upload aos headers. Arquivos ficam criptografados com a APP_KEY em `storage/app/private/downloads`, fora de `public/`. O banco mantém tamanho/hash SHA-256; integridade e autorização são conferidas antes da entrega. Resposta `application/octet-stream`, attachment, no-store e CSP sandbox. Upload seguido de falha no banco remove o arquivo órfão. Não existem URLs públicas permanentes.

**Limites:** sem antivírus/CDR, geração/ativação de licença de software, DRM, limite agregado de armazenamento ou política automática de retenção. A equipe é responsável por verificar o material e monitorar disco. Revogar acesso não apaga cópias previamente baixadas. Faça backup **conjunto de banco + arquivos privados + APP_KEY**. Não gere outra chave em atualizações.

## Testes e operação

`CommercialContentTest` cobre autorização/IDOR, valores/limites, estados, revisão, publicação agendada, escape, acesso por produto, corrupção, rollback e limpeza de arquivo. A suíte multiprocesso cobre 20 aceites simultâneos e corrida entre aceite/retirada.

`tests/browser_commercial.py` usa Chromium e fixture isolada (`tests/commercial_browser_fixture.php`, APP_ENV=local, LAGOS_TEST_MODE=1, DB_CONNECTION=sqlite, DB_DATABASE apontando exclusivamente para `.cache/commercial-browser.sqlite`). Rode migrations nesse banco descartável e sirva a aplicação local com as mesmas variáveis; nunca use a base real. O teste exercita criação/aceite/fatura, publicação/histórico, upload/download e larguras 390/768/1440. Relatório: `BROWSER-COMMERCIAL-RESULTS.json`. Outros relatórios de navegador continuam históricos.

A migration `000014_commercial_content` cria quatro tabelas; `000013_hosting_access` introduz o vínculo Plesk. Ambas bloqueiam rollback quando apagariam registros. Atualizações exigem migrations, caches coerentes e restart dos workers. Os novos módulos não configuram provedores, SMTP, gateways ou infraestrutura por conta própria.
