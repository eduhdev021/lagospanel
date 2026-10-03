# Atendimento — atualizado na alpha.7

## Fluxos disponíveis

Cliente: abertura com prioridade, resposta pública, anexo privado, download, encerramento e reabertura dos próprios chamados. Equipe: leitura, resposta pública, nota interna, atribuição de responsável, prioridade e filtros por estado/departamento/responsável/prazo. Não há botão fictício: os formulários usam persistência e verificações no servidor.

`support.view` permite ler todos os chamados, inclusive notas e anexos internos; `support.manage` permite responder e fazer triagem. Atribuição organiza a fila, **não restringe acesso ao responsável**. Não há isolamento entre departamentos. Responsáveis devem ter ambas as permissões. A administração exige 2FA em produção, inclusive para downloads. Permissões são verificadas a cada requisição.

Notas internas não aparecem nem nas listas nem no histórico do cliente; seus anexos também não são baixáveis pela rota do cliente. Notas não encerram chamados, não contam como resposta ao cliente, não alteram prazo e não enviam e-mail. Mesmo o cliente titular de um chamado não recebe essas notas.

## Prazos

| Prioridade | Meta de resposta |
|---|---:|
| Baixa | 48 horas corridas |
| Normal | 24 horas corridas |
| Alta | 8 horas corridas |
| Urgente | 2 horas corridas |

O prazo fica gravado no chamado. Resposta pública da equipe limpa o prazo pendente e registra a primeira resposta, quando aplicável. Novo contato do cliente inicia outro prazo apenas se não houver um pendente. Complementos não empurram o relógio. A triagem pode antecipar o vencimento, mas reduzir a prioridade não prorroga um prazo já pendente. A nova prioridade vale para os próximos ciclos de resposta. Reabertura repetida não renova o prazo novamente.

Chamados antigos não recebem prazo histórico artificial: próximo contato/reabertura/triagem inicia a política atual. A exibição usa a configuração UTC padrão da aplicação. Não há calendário comercial, feriados, escalonamento automático, metas por plano/departamento, compensação contratual ou relatório histórico de cumprimento de SLA. São controles operacionais básicos, não uma garantia de nível de serviço.

A lista mostra as 20 mensagens mais recentes por chamado. “Histórico completo” permite consultar páginas de 30, mais recentes primeiro; dentro de cada página as mensagens aparecem em ordem cronológica. Notas internas não ocupam páginas do cliente.

## Arquivos e privacidade

- Upload pela interface: TXT, PNG, JPG/JPEG e PDF, com extensão e MIME identificado no conteúdo via Fileinfo. Tipos não reconhecidos são recusados; não basta declarar `Content-Type` no navegador.
- Até 3 arquivos por mensagem, 2 MiB por arquivo, 30 arquivos e 10 MiB por chamado.
- Quota compartilhada por conta: 50 MiB por padrão, configurável por `SUPPORT_ATTACHMENT_ACCOUNT_MIB` entre 10 e 1024. Inclui arquivos enviados pela equipe aos chamados daquela conta, inclusive notas internas. A quota não é por remetente nem é reiniciada criando outro chamado.
- Limites são verificados sob transação e bloqueios. Falha de quota/armazenamento reverte a mensagem e seus arquivos. Ao atingir a quota, mensagens sem arquivo continuam disponíveis.
- Bytes são armazenados criptografados na tabela `ticket_attachments`, não na pasta pública. Metadados da listagem não carregam o payload. SHA-256 é verificado no download. Falha de MAC ou hash retorna 409 sem devolver o conteúdo.
- Downloads exigem sessão, conta verificada e autorização do proprietário ou da equipe; não há URL pública/signed temporária. O servidor força `attachment`, `application/octet-stream`, `nosniff`, CSP restritiva e `no-store`.
- Criptografia **em repouso**, não ponta a ponta: operadores autorizados e quem possui banco + chave podem ler. Guarde `APP_KEY` em backup separado e protegido; perdê-la inviabiliza decifrar arquivos. Não rotacione a chave sem plano para os dados existentes.
- **Não há antivírus, CDR, garantia de arquivo inofensivo, armazenamento de objetos/S3, exclusão/retencão automática ou processo LGPD completo.** Um PDF pode conter conteúdo malicioso mesmo com MIME correto. Não abra anexos desconhecidos sem proteção adequada. Upload de executáveis/HTML/SVG não é permitido, mas whitelist não substitui varredura.

Planeje pelo menos 3 vezes o volume bruto para representação criptografada no banco, além de WAL, backups e retenção. PHP deve permitir `upload_max_filesize` de pelo menos 2M e `post_max_size` de pelo menos 8M; mantenha limites equivalentes no proxy. Fileinfo é obrigatório. Em futura homologação MySQL/MariaDB, configure InnoDB e `max_allowed_packet` de pelo menos 16 MiB. Esta entrega foi testada em SQLite, não comprova comportamento de produção nesses bancos.

## E-mail e auditoria

Resposta pública da equipe agenda um aviso ao cliente proprietário em fila persistente, dentro da mesma transação. O e-mail contém somente o número do chamado e link ao painel, não o assunto, mensagem, nota ou arquivo. No envio, a propriedade é verificada novamente. Falha na inserção da fila reverte a resposta. O worker continua necessário; entrega externa e exatamente-uma-vez por SMTP não são garantidas. Uma mensagem pode ser reenviada em retries.

Notas internas não enviam aviso. Novos chamados, atribuições e estouros de prazo ainda não disparam notificações. A equipe deve monitorar a fila. Operações de abertura, resposta, nota, triagem e fechamento/reabertura são auditadas sem copiar o conteúdo da mensagem para auditoria.

POST de abertura/resposta não oferece idempotência: reenvio pode criar outra mensagem. O endpoint de API cria chamado usando a mesma política de prioridade/prazo, mas não aceita anexos ou notas internas.

## Evidência local

`tests/Feature/SupportTest.php`, `tests/browser_support.py` e os cenários adicionais de `tests/concurrency.py`. Cobre privacidade/IDOR, RBAC/2FA, formatos, limites, criptografia, corrupção, rollback, fila, prioridade, paginação e concorrência. Não equivale a pentest, homologação de SMTP, benchmark, varredura antimalware ou teste completo de restauração.

O downgrade do esquema é bloqueado quando há anexos ou notas internas: remover a coluna de privacidade exporia as notas no código antigo. O ciclo down/up foi testado somente com dados sem esses conteúdos sensíveis; não é restauração operacional.

## Respostas prontas

Biblioteca acessível pelo Atendimento. `support.view` permite consulta; `support.manage` permite criar, editar e desativar. Departamento restringe o uso no seletor, não a leitura pela equipe. Modelos são texto simples e não devem conter segredos/dados pessoais. Ao inserir, o texto existente é preservado e a resposta não é enviada: a equipe revisa e envia explicitamente. Detalhes em `DOCUMENTOS-E-MODELOS.md`.

## Chat de IA

Chat com IA é separado dos tickets. Não lê tickets/notas/anexos nem acessa contas automaticamente. Conversas pertencem ao titular e são enviadas, mediante ciência, ao endpoint Ollama configurado. Link para chamado humano não transfere histórico automaticamente. Chave e modelos são configurados por equipe com permissão de integrações. Veja `OLLAMA.md`. Não é chat humano em tempo real.
