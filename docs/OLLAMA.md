# Chat de suporte com Ollama — alpha.8

## Configuração pelo ADM

1. ADM → Integrações → **Configurar chat Ollama e modelos**. Leitura exige `integrations.view`; alterações/consulta remota exigem `integrations.manage`, com autenticação verificada e 2FA da equipe em produção.
2. Informe a origem, sem `/api`: `https://ollama.com` para cloud, ou origem HTTPS de servidor com API **nativa Ollama**. Não é um adaptador genérico OpenAI `/v1`.
3. Informe a API key; deixe habilitação desmarcada e salve. Campo vazio mantém a chave anterior; caixa específica permite removê-la. Chave nunca é reexibida, serializada para cliente ou enviada ao navegador. É criptografada com `APP_KEY`, que precisa ser preservada.
4. Clique **Consultar modelos com a chave salva**. O servidor faz `GET /api/tags` usando Bearer quando há chave. Modelos são listados no seletor; não há nomes inventados nem download/pull de modelos.
5. Escolha o modelo retornado, marque habilitar e confirme ciência do envio de dados/custos; salve. A seleção é validada no servidor contra o catálogo dessa configuração. **Listagem não garante cota/autorização de inferência**: isso depende do provedor, modelo e conta. Teste uma conversa antes de liberar aos clientes.
6. Ao mudar URL/chave, salve desativado: catálogo/seleção são limpos. Consulte novamente. Alterações de configuração incrementam versão e invalidam solicitações pendentes da versão anterior. Edição administrativa desatualizada retorna 409.

Ollama local normalmente não exige key. HTTP é recusado por padrão. Para um daemon no mesmo servidor, o operador pode configurar `OLLAMA_ALLOW_LOOPBACK_HTTP=true`, reconstruir caches/reiniciar workers e usar exclusivamente `http://127.0.0.1:11434`, `http://localhost:11434` ou IPv6 loopback. É o servidor do LagosPanel, **não o computador do usuário**. Não exponha a porta 11434 publicamente. Para máquina remota, use HTTPS, autenticação/proxy e restrição de rede. Não é permitido HTTP para IP privado remoto nesta implementação.

A configuração de destinos é privilégio administrativo; não há allowlist de rede que proteja contra administrador malicioso. Restrinja tráfego de saída e permissões de integrações. Certifique-se de que URL/provedor e política de privacidade são autorizados pela organização.

## Conversa do cliente

Menu **Chat com IA**. O cliente cria conversa, escreve mensagem, confirma ciência do envio ao provedor e aguarda. Respostas são geradas em fila; o navegador atualiza por consultas locais. Sem JavaScript é possível usar “Atualizar conversa”. Após cerca de dois minutos de polling, a página orienta atualizar manualmente; não reenfileira a inferência.

Há link para abrir chamado humano. Ele **não transfere histórico automaticamente** e não constitui chat humano ao vivo. IA se identifica como assistente, avisa que pode errar e não promete ações/consulta de conta.

O modelo recebe somente:
- instrução fixa de suporte, sem ferramentas;
- a mensagem atual (até 2.000 caracteres);
- até oito trocas concluídas da mesma conversa, respeitando orçamento de 16.000 caracteres de histórico.

Não recebe automaticamente nome/email do cliente, faturas, serviços, tickets, notas internas, anexos, API keys ou credenciais dos painéis. Não há RAG/base de conhecimento automática, arquivos, imagens, voz, ferramentas, execução de código ou comandos administrativos. O prompt orienta não pedir segredos nem recomendar ações destrutivas sem avaliação humana, mas **não garante que toda resposta seja correta/segura**. A fronteira técnica é a ausência de ferramentas/acesso, além da revisão humana.

## Privacidade e limites

- Rotas de conversa, envio, estado e exclusão exigem titular autenticado e verificado. Não existe tela de leitura de conversas de clientes para a equipe nesta versão. Acesso a banco + APP_KEY continua permitindo descriptografar mensagens.
- Texto de usuário e resposta são criptografados em repouso no banco; são enviados legíveis ao provedor para inferência. Não é criptografia ponta a ponta.
- Respostas são exibidas como texto escapado/`textContent`, sem HTML/Markdown executável, links automáticos ou tool calls. Chamadas de ferramentas retornadas pelo provedor são recusadas e nunca executadas.
- Até 20 conversas por conta, 20 mensagens por conversa, uma inferência pendente por usuário e 30 solicitações diárias por conta (UTC), inclusive falhas. Limites de taxa adicionais nas rotas. Contabilização diária permanece após excluir conversa; não há reset pelo usuário. Use também cotas/custos no provedor.
- Solicita `num_predict=1024` e `num_ctx=8192`; suporte/aplicação desses parâmetros depende do provedor/modelo. Resposta HTTP limitada a 1 MiB; aceita apenas resposta final textual, até 32.000 bytes, e conserva até 8.000 caracteres. Esses limites não constituem garantia de faturamento do provedor.
- Exclusão remove a conversa/mensagens deste banco e impede publicação de resposta atrasada. **Não cancela requisição remota já enviada**, não apaga retenção do provedor nem backups. Não há expiração automática, anonimização completa ou exportação LGPD implementada; estabeleça política operacional e termos adequados.
- Auditoria registra IDs/eventos e alterações de configuração sem corpos de mensagens, resposta bruta ou token. Rever logs de infraestrutura/provedor separadamente.

## Fila, falhas e segurança de transporte

API usada: `POST /api/chat`, `stream=false`, com modelo escolhido e mensagens produzidas pelo servidor. Bearer apenas no header. Sem redirects, TLS verificado e sem retry automático; connect timeout 3s, catálogo 10s, inferência 45s, job 65s. Mantenha worker persistente e worker com timeout 75s e queue retry_after acima de 75s (padrão90). Sem worker, mensagens permanecem na fila; configurar key/modelo não inicia infraestrutura de execução.

Reenvio do mesmo identificador de mensagem não cria outra inferência/debito de cota; corpo diferente com a mesma referência retorna 409. Jobs duplicados são reivindicados sob transação. Não há promessa de exatamente-uma-vez no provedor: timeout pode consumir tokens sem resposta local e não será reenviado automaticamente.

Ao enviar nova mensagem, pendências do mesmo usuário com mais de cinco minutos são marcadas como falha sem reenvio. Execuções antigas não podem publicar depois. Mudança/desativação de configuração antes do envio impede chamada; se ocorrer durante a inferência, impede publicação tardia, mas não recolhe dados já enviados. Erros exibidos são genéricos, sem texto bruto do provedor.

Não há streaming de tokens, API pública de chat, orçamento global de todos os usuários, medição financeira por token ou filas separadas por recurso. Capacidade precisa ser dimensionada: um modelo lento pode ocupar worker compartilhado com cobrança/provisionamento. Consulte `OPERACAO.md`.

## Homologação real (pendente)

- Testar API key real de menor privilégio, catálogo/seleção, direito de inferência, cobrança e quotas do modelo escolhido.
- Validar endpoint cloud ou servidor local/reverso, TLS, firewall, clock e política de logs/retencão.
- Conferir modelo carregado, RAM/VRAM, tempo de carga, prompts longos, suporte aos parâmetros e impacto em fila.
- Exercitar chave inválida/rotacionada, modelo removido, quota esgotada, timeout, pausa/exclusão durante inferência e ausência de publicação tardia.
- Validar isolamento com dois clientes, respostas maliciosas como texto, consentimento e disponibilidade do chamado humano.
- Revisar termos, licença do modelo, dados enviados, retenção do provedor e política LGPD. Não enviar segredos reais nos testes.

## Evidência e fontes

**Nenhuma inferência em Ollama real foi executada.** PHPUnit cobre rotas/permissões e contrato HTTP simulado. No navegador, o POST administrativo de catálogo é interceptado pelo harness e delegado ao controlador local com `Http::fake`; seleção/configuração usam a UI persistente. Inferência usa o worker real com transporte simulado. Concorrência inclui 20 envios idempotentes e 20 jobs duplicados, com uma chamada simulada.

Referências oficiais consultadas em 03/10/2026:
- https://docs.ollama.com/api/authentication
- https://docs.ollama.com/api/tags
- https://docs.ollama.com/api/chat

Resultados: `OllamaTest.php`, `BROWSER-PTERO-AI-RESULTS.json`, `CONCURRENCY-RESULTS.json`. Simulação não comprova acesso, qualidade, segurança de respostas ou desempenho de um modelo real.


## Pesquisa na internet — harness da aplicação

1. Em **ADM → Ollama**, habilite pesquisa web. Use uma chave de Ollama Cloud no campo separado de pesquisa. Somente quando a inferência usa exatamente `https://ollama.com` a busca pode reutilizar sua chave principal. Chaves de endpoints próprios/locais nunca são encaminhadas ao serviço cloud.
2. No chat, marque **Pesquisar na internet**, preencha a consulta pública (até 400 caracteres) e aceite o consentimento da pesquisa. O consentimento de inferência permanece separado.
3. O job chama `POST https://ollama.com/api/web_search` com `query` e `max_results=5`, antes da inferência. A busca recebe somente essa consulta: não recebe o histórico da conversa, os dados da conta nem a chave do servidor local. Não coloque segredos na consulta.
4. O modelo recebe o histórico normal e os trechos encontrados, como evidência não confiável em mensagem de usuário, nunca como instrução de sistema. Não precisa implementar ferramentas ou navegação nativas. Continua limitado à interface textual `/api/chat` suportada pelo painel.
5. Até cinco fontes aparecem na conversa com título e link. O prompt solicita referências `[1]`, mas **a aplicação não garante que o modelo cite corretamente nem que a fonte confirme sua conclusão**. Os resultados são trechos de busca; não representam leitura integral da página.

A busca usa TLS verificado, sem redirects/retries, conexão 3s, resposta 8s e teto de 1 MiB. Cada trecho tem até 1600 caracteres e título até 200; duplicatas/URLs inválidas são descartadas. Não acessamos as URLs retornadas: não há `web_fetch`, JavaScript externo, navegação autônoma ou ações em contas/servidores. Filtros de URL não constituem validação DNS ou certificação de reputação do destino.

Consulta e fontes são criptografadas no banco; estado e exclusão respeitam o titular. Trechos brutos/chaves não são entregues no JSON de polling. Pesquisa indisponível ou sem resultados é sinalizada separadamente, sem fabricar lista de fontes; o modelo ainda pode responder com conhecimento prévio. Instruções maliciosas nos resultados são tratadas como dados, mas isso não elimina todo risco de prompt injection ou alucinação. Não há ferramentas privilegiadas disponíveis ao modelo.

Desativação/troca de configuração e exclusão são rechecadas após pesquisar e antes de publicar; não é possível recolher uma requisição já enviada. Reenvios idempotentes não criam nova busca. Cotas/custos e política de retenção do provedor continuam sob responsabilidade do operador. Pesquisa exige conectividade de saída e autorização/limites da conta Ollama Cloud; não se torna disponível por apenas instalar um modelo local.

Contrato consultado: https://docs.ollama.com/capabilities/web-search em 03/10/2026. **Teste de pesquisa real ainda pendente**: validação atual usa respostas simuladas, inclusive conteúdo hostil, falhas, privacidade e revogação. Evidência: `WebAndSiteTest.php`, `BROWSER-WEB-SITE-RESULTS.json`.
