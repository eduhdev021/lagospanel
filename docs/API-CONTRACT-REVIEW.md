# Conferência dos contratos de API — 03/10/2026

## Resultado

**Nos fluxos e campos revisados de Ollama, Pterodactyl Application API, aaPanel clássico e WHM API 1, não foi identificada divergência bloqueante entre a implementação e as referências consultadas.** Isso sustenta a expectativa de funcionamento quando a versão, as credenciais e os recursos do provedor atendem ao contrato. Não certifica APIs inteiras, forks, instalações não informadas, gateways de pagamento ou todas as versões desses produtos.

| Integração | Conferência efetuada | Resultado / limite |
|---|---|---|
| Ollama — catálogo | GET `/api/tags`, lista `models[].name` e parser do painel | **Chamada real ao catálogo público passou: 17 modelos.** HTTPS pelo próprio serviço `App\Services\Ollama`, sem mock, token ou alteração de dados. Não prova permissão de inferência. |
| Ollama — chat | POST `/api/chat`, `model`, `messages`, `stream=false`, opções, `done` e `message.content` | Compatível nos campos revisados. Chat textual somente; tool calls são recusadas por projeto. Inferência autenticada não foi executada. |
| Ollama — pesquisa | POST cloud `/api/web_search`, Bearer, `query`, `max_results=5`, `results[].title/url/content` | Compatível com a documentação REST. O harness fornece os trechos ao modelo sem exigir ferramenta nativa. Pesquisa autenticada não foi executada. |
| Pterodactyl | Bearer/Application API; usuário por ID; servidor por external_id; criação com limits/feature_limits/deploy; criação201; mutações sem conteúdo; suspensão/reativação/exclusão e estados do transformer | Campos/tipos e rotas revisados compatíveis com `1.0-develop` consultado. O campo `suspended` ainda existe, mas está depreciado; o driver também exige `status`. Não houve execução em instalação Pterodactyl/Wings. |
| aaPanel | POST form, assinatura `md5(timestamp + md5(key))`, cookies, listagem, versões PHP, AddSite, DeleteSite, SiteStop/SiteStart | Compatível com o PDF oficial clássico. Exclusão omite flags `path`, `ftp` e `database`, como orientado para não excluir esses recursos. Não certifica variantes/versões modernas sem verificar o servidor. |
| WHM/cPanel | Header `whm usuário:token`, portas HTTPS, `api.version=1`, operações createacct/listaccts/suspendacct/unsuspendacct/removeacct e parâmetros principais | Autenticação e operações conferidas. Transporte POST form corroborado pelo cliente público oficial; não foi trocado para GET com senha na URL. Sem chamada a WHM licenciado. |

## Verificação executada

- Resultado da chamada real pública: `OLLAMA-PUBLIC-CATALOG-CHECK.json`.
- Testes locais relacionados: `API-CONTRACT-TESTS.txt`; transportes de chat, pesquisa e provisionamento são simulados nesses testes.
- Cópia limpa do código, sem `.env` nem pasta Unit residual: **249 testes, 1100 asserções e 16 cenários de concorrência passaram** após a correção de configuração do PHPUnit. Composer audit dessa cópia sem advisories/abandonados.
- Nenhum servidor, site, conta, pagamento ou pesquisa paga foi criado/alterado para esta revisão. Nenhuma credencial GitHub foi enviada aos provedores.

## Falha real identificada e corrigida no CI

O primeiro workflow remoto falhou. A configuração `phpunit.xml` apontava para `tests/Unit`, pasta vazia presente no workspace, mas ausente no Git e nos pacotes. A reprodução por `git archive` falhou com `Test directory .../tests/Unit not found`, antes de rodar testes. Removida somente a entrada da suíte inexistente; a suíte Feature continua incluindo os 249 testes. Não foram removidos testes, afrouxadas asserções nem desabilitado o CI.

Essa falha é independente da API do provedor. A execução local na cópia limpa passou; resultado remoto atualizado deve ser conferido no [GitHub Actions](https://github.com/eduhdev021/lagospanel/actions).

## O que depende da instalação do operador

- Chave correta, ACLs, allowlist/IP de saída, TLS, firewall e relógio.
- Ollama: modelo realmente capaz de chat, disponibilidade/RAM/VRAM, permissão e cota para inferência/pesquisa. O timeout local de45s pode rejeitar modelos lentos ou carregamento inicial; isso não é uma incompatibilidade do endpoint.
- Pterodactyl: Application Key, usuário já vinculado, egg/imagem/startup/variáveis, Wings/nó/localização e alocações disponíveis. O painel precisa concluir a instalação remota.
- aaPanel: API habilitada, IP autorizado, webserver e versão PHP instalados; esse driver gerencia sites, não contas de hospedagem isoladas.
- WHM: licença, token/ACLs, pacote disponível e limites do revendedor/servidor.
- LagosPanel: integrações habilitadas explicitamente, fila/worker e configuração operacional correta.

**Contrato compatível não substitui uma transação real:** um HTTP401/403 por chave/ACL, HTTP429 por cota ou erro de capacidade/egg pode ocorrer com uma requisição perfeitamente válida. O teste mínimo de ponta a ponta deve usar recursos descartáveis e credenciais configuradas com segurança no ADM, não enviadas pelo chat.

## Referências oficiais consultadas

- https://docs.ollama.com/api/tags
- https://docs.ollama.com/api/chat
- https://docs.ollama.com/capabilities/web-search
- https://github.com/pterodactyl/panel/blob/1.0-develop/routes/api-application.php
- https://github.com/pterodactyl/panel/blob/1.0-develop/app/Http/Requests/Api/Application/Servers/StoreServerRequest.php
- https://github.com/pterodactyl/panel/blob/1.0-develop/app/Transformers/Api/Application/ServerTransformer.php
- https://github.com/pterodactyl/panel/blob/1.0-develop/app/Models/Server.php
- https://www.aapanel.com/Document/api.pdf
- https://api.docs.cpanel.net/whm/introduction.md
- https://api.docs.cpanel.net/whm/tokens.md
- https://api.docs.cpanel.net/specifications/whm.openapi/account-creation/accounts-createacct.md
- https://api.docs.cpanel.net/_bundle/specifications/whm.openapi.json?download

Suporte a POST/form na interface oficial cPanel: [3](https://github.com/CpanelInc/cPanel-PublicAPI/blob/master/lib/cPanel/PublicAPI.pod). A descrição OpenAPI consultada anuncia GET; o cliente oficial documenta ambos. O parâmetro legado `forcedns` consta na página descritiva de createacct consultada, embora não apareça no conjunto de parâmetros da especificação JSON consultada; o driver envia0, não autorização para sobrescrever DNS. Isso deve ser confirmado na versão instalada, não ocultado como uma validação completa de schema.

Hashes de referências baixadas: `API-REFERENCE-HASHES.json`. São rastreabilidade da consulta, não assinatura do provedor nem homologação de uma versão de produção.
