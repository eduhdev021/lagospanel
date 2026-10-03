# aaPanel — sites gerenciados (alpha.5)

## Contrato e limites

Adaptador nativo da API clássica documentada em https://www.aapanel.com/Document/api.pdf (consultada em 03/10/2026). Não houve acesso a aaPanel real; compatibilidade com versões específicas e APIs v2 não foi homologada.

O recurso provisionado é **um site gerenciado pela equipe**, não uma conta isolada de hospedagem. Não são criados usuários de painel, FTP, bancos, caixas postais, quotas, SSL ou DNS. Não existe login aaPanel para o cliente nem compartilhamento da chave administrativa. A publicação fica a cargo do operador. Não vender este recorte como hospedagem isolada para código PHP de clientes não confiáveis: sites podem compartilhar usuário/permissões do servidor.

## Configuração

1. Use servidor de homologação, backup independente e API habilitada no aaPanel. Restrinja a whitelist ao IP de saída do LagosPanel; sincronize relógios. Restrinja também o tráfego de saída por firewall. O administrador pode cadastrar destinos de rede: a validação de URL não é uma proteção contra administrador malicioso.
2. Use origem HTTPS com certificado válido, inclusive em portas próprias (ex.: `https://painel.exemplo.com:7800`). Não inclua caminho de entrada de segurança, usuário, query ou fragmento. Não desabilite TLS. Se sua versão exigir outro contrato, não contorne a validação: o adaptador precisa ser homologado/adaptado.
3. Em Integrações, escolha aaPanel, informe a chave API, prefixo de duas letras minúsculas e confirme o uso nativo. Chaves ficam criptografadas com `APP_KEY`; preserve essa chave em atualizações e backups. Rotação e pausa são suportadas; não altere o destino de serviços existentes.
4. No produto, selecione a integração, domínio-base ASCII/punycode sob seu controle e código PHP instalado (`82` para PHP 8.2, `00` para estático). O driver consulta as versões antes de criar. Configure DNS separadamente. Use prefixos exclusivos entre instalações que compartilham servidor/domínio.
5. Mantenha o produto indisponível até homologar. `NATIVE_PROVISIONING_ENABLED=false` bloqueia chamadas, **não vendas**. Só habilite após conferir credenciais, backups, workers, fila e roteiros abaixo. Recarregue config e reinicie workers conforme `OPERACAO.md`.
6. Opções configuráveis selecionadas são bloqueadas até existir mapeamento. Não prometa quotas que o driver não aplica.

## Protocolo implementado

- Form POST com `request_time` e `request_token = md5(timestamp + md5(api_sk))`, conforme protocolo do provedor. Não é algoritmo de armazenamento de senhas do LagosPanel. Credenciais não vão na URL. Cookies da API permanecem apenas em memória.
- TLS verificado, redirects bloqueados, connect timeout 5s/timeout 10s, limite de resposta 1 MiB. Sem retries automáticos de mutações.
- Consulta: `/data?action=getData&table=sites`; comparação exata do domínio e validação de ID positivo, path, marcador e status. Listagem inválida, ambígua, truncada ou busca não vazia sem correspondência exata não comprova ausência.
- Criar: `/site?action=GetPHPVersion`, depois `/site?action=AddSite`, com `ftp=false` e `sql=false` em campos textuais; verifica `siteStatus` e faz nova consulta. Não persiste resposta bruta nem credenciais inesperadas de FTP/banco. Criação inesperada desses recursos exige revisão.
- Suspender/reativar: `/site?action=SiteStop` e `SiteStart`, com ID/nome e confirmação posterior.
- Encerrar: `/site?action=DeleteSite`, com ID e `webname`. **Omite** flags `path`, `ftp` e `database`, preservando arquivos e recursos associados conforme contrato documentado. Exige confirmação administrativa. Não remove conteúdo do disco nem implementa política de retenção; limpeza deve ser operacional e auditada.

Cada serviço guarda um snapshot com endpoint, domínio, versão PHP, marcador UUID `LagosPanel:...` e diretório `/www/wwwroot/{domínio}-{UUID}`. O nonce evita reutilizar automaticamente diretórios preservados de outro provisionamento. Não edite path/marcador manualmente no aaPanel: a divergência bloqueia operações até investigação. Isso não substitui isolamento de sistema operacional.

## Incerteza, conciliação e concorrência

O driver herda as travas, reivindicação de execução, deduplicação e verificações financeiras do provisionamento nativo. Jobs locais duplicados não devem enviar duas criações. Cada envio revalida pausa/chave e destino. O sucesso local exige confirmação remota.

Timeout/falha pode significar que a operação ocorreu remotamente. `sent_at` registra tentativa, não conclusão. Operações incertas vão para revisão: consultar, conferir identidade/estado e confirmar o ID numérico observado, ou autorizar uma nova tentativa com justificativa e confirmações. **Antes de reenviar, pare/confira worker antigo e tarefa remota.** Uma consulta de ausência não prova que uma requisição anterior terminou. Não existe garantia distribuída de exatamente-uma-vez, cancelamento remoto ou importação automática de sites existentes.

## Roteiro obrigatório em servidor real (pendente)

- Registrar versão/edição, sistema, webserver, PHP, certificado, porta e contrato API.
- Validar whitelist permitida/negada, chave inválida/rotacionada, clock skew e TLS inválido; conferir logs de proxy/aaPanel para não registrar segredos.
- Criar site estático e PHP de teste após pagamento; confirmar ID, domínio, path nonce e marcador, ausência de FTP/banco e comportamento HTTP/DNS.
- Suspender/reativar e testar cobrança/reativação, repetição de job e pausa entre leitura e mutação.
- Simular timeout após criação, executar conciliação com operador e verificar ausência de duplicação/reenvio inseguro.
- Criar arquivo sentinela no diretório de teste; encerrar com confirmação; provar remoção da configuração **e preservação do arquivo**. Conferir bancos/FTP não afetados.
- Testar colisão/alteração manual de ID, domínio, marcador e caminho: devem bloquear mutação.
- Validar backup/restore, permissões dos diretórios e política de retenção. Não disponibilizar código não confiável sem arquitetura de isolamento validada separadamente.

Evidência local: `AaPanelTest.php`, `BROWSER-AAPANEL-RESULTS.json` e cenário de 20 processos no relatório de concorrência. Simulações validam nosso contrato, não o comportamento de uma instalação real.
