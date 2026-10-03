# Desenvolvimento consolidado — main

Última release estável: **1.3.0**. Código da main: **1.4.0-dev**. Este documento descreve desenvolvimento posterior à base, não uma nova release nem a conclusão da paridade WHMCS/Paymenter. O visual existente foi mantido. Não houve deploy nem chamadas autenticadas a provedores reais.

## Módulos comerciais e conteúdo

Orçamentos com aceite e fatura única, avisos/incidentes/manutenções e downloads privados por serviço ativo. Consulte [COMERCIAL-E-CONTEUDO.md](COMERCIAL-E-CONTEUDO.md) para configuração, permissões, limites e testes.

## Clientes Plesk isolados e acesso temporário

Além do proprietário preexistente (`owner_id`), produtos Plesk aceitam modo automático explícito:

```json
{
  "domain_suffix": "clientes.exemplo.com",
  "ip": "203.0.113.10",
  "plan_guid": "01234567-89ab-4cde-8123-456789abcdef",
  "auto_customer": true
}
```

Substitua os exemplos pelo IP e GUID reais. **Não combine `owner_id` com `auto_customer:true`.** Produtos e serviços antigos não são convertidos nem ganham acesso à conta compartilhada.

- Checkout exige e-mail verificado e integração nativa ativa. Não cria cliente remoto antes do pagamento.
- Após pagamento, a fila prepara um cliente por integração/usuário, confere login, e-mail, identificador externo, ID e estado; somente então vincula o proprietário ao snapshot da assinatura e libera seu provisionamento.
- Reuso de cliente confirmado exige nova leitura remota. Mudança de e-mail, integração desativada ou identidade divergente bloqueiam a preparação.
- Senha aleatória temporária de 14 caracteres, criptografada no banco, apagada após confirmação. Não é enviada ao cliente, sessão, auditoria ou página administrativa.
- Exclusividade no banco, lease e token de execução evitam criação concorrente. Depois de `sent_at`, nunca há segundo POST de criação: um timeout é recuperado por leitura, não por repetição.
- O ADM consulta **Integrações → Clientes Plesk**. Em erro/interrupção, confira o provedor e use **Operações → Retomar preparação de conta**. Se o envio ficou incerto e o cliente não existir remotamente, a solicitação permanece em revisão; não apague registros nem zere `sent_at` para contornar a proteção.
- **Entrar no Plesk** está disponível somente para o titular de assinatura ativa com cliente isolado confirmado. Exige senha LagosPanel e código 2FA novo, quando habilitado. Operação pendente/em revisão, cliente divergente ou assinatura remota inativa bloqueiam o acesso.
- Sessão XML `server/create_session`, `login` do cliente verificado, `data/user_ip` em base64. URL reconstruída na origem HTTPS fixa: `/enterprise/rsession_init.php?PLESKSESSID=...`. Sem expor a chave administrativa; resposta privada `no-store`/`no-referrer`. Cliente e assinatura são conferidos antes e depois da emissão.
- Atrás de proxy, configure somente proxies confiáveis para que o IP observado corresponda ao navegador. Não confie indiscriminadamente em `X-Forwarded-For`. O token vinculado a IP incorreto pode não funcionar; não removemos esse vínculo para contornar configuração de rede.
- Cancelar uma assinatura não apaga o cliente Plesk: ele pode possuir outras assinaturas. Remoção de clientes e gestão avançada ainda não foram implementadas.

## Atualização do ambiente de desenvolvimento/homologação

Faça backup do banco, preserve a `APP_KEY`, instale as dependências do lockfile e execute `php artisan migrate --force`, seguido de `php artisan queue:restart`. As migrations novas criam `plesk_customer_requests`, `quotes`, `bulletins`, `bulletin_updates` e `download_assets`; não alteram dados de serviços anteriores. Rollback com registros é bloqueado. Não reutilize uma chave nova em banco com credenciais já criptografadas. Não houve atualização de servidor de produção nesta execução.

## Evidência

- **413 testes PHP / 2.595 assertions**, incluindo preparação Plesk, recuperação, identidade, autorização, sessão, administração e módulos comerciais/conteúdo.
- **23 cenários multiprocesso** passando, incluindo 20 serviços pagos concorrentes convergindo em um cliente Plesk com um único POST simulado.
- Respostas XML e HTTP simuladas. Não são homologação de Plesk real, validação XSD ou pentest. O navegador foi exercitado nos módulos comerciais/conteúdo; não no SSO real Plesk.
- **40 verificações Chromium** dos novos fluxos comerciais/conteúdo, incluindo três larguras de tela, sem erros JavaScript ou assets ausentes.
- Resultados atuais: `PHPUNIT-RESULTS.txt`, `CONCURRENCY-RESULTS.json` e `CONCURRENCY-RESULTS.txt`. Relatórios anteriores de navegador continuam históricos.

## Referências oficiais consultadas

- [Criar clientes](https://docs.plesk.com/en-US/onyx/api-rpc/about-xml-api/reference/managing-customer-accounts/creating-customer-accounts.28789/)
- [Consultar clientes: filtros e exemplos completos](https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-customer-accounts/getting-information-about-customer-accounts.28790/)
- [Campos de criação](https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-customer-accounts/customer-settings/general-customer-account-settings/type-clientaddgeninfo.34336/)
- [Campos retornados](https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-customer-accounts/customer-settings/general-customer-account-settings/type-clientgetgeninfo.34582/)
- [Tokens de sessão](https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-plesk-server/creating-session-tokens.73865/)
- [Login automático](https://docs.plesk.com/en-US/obsidian/administrator-guide/plesk-administration/automatic-logging-in-to-plesk.80002/)

## Escopo geral ainda aberto

A matriz de paridade continua incluindo pendências reais: upgrades/prorrata e alterações remotas de planos; DirectAdmin SSO e gestão avançada; console, backups e reinstalação Pterodactyl; VPS/cloud e registradores; organizações/subcontas; moedas/impostos, pagamentos parciais/reembolsos e outras funções financeiras; extensões/importadores. Esta alteração não resolve nem remove essas metas e não será descrita como entrega integral.
