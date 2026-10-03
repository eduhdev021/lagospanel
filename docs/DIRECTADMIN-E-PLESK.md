# DirectAdmin e Plesk — base pública 1.3.0

> O modo automático de clientes e o SSO Plesk, posteriores a esta base e incluídos no desenvolvimento da main, estão documentados em [DESENVOLVIMENTO.md](DESENVOLVIMENTO.md). As limitações abaixo descrevem a release 1.3.0.

Drivers nativos ligados a contratação, pagamento, fila, suspensão, reativação, encerramento e conciliação. Não usam conector JSON genérico nem scripts enviados pelo cliente. HTTPS verificado, sem redirects/retry automático, conexão 3 s, resposta 8 s e limite de 1 MiB. Senhas remotas aleatórias são criptografadas com APP_KEY; nenhuma senha LagosPanel é enviada ao provedor. HTTP fora das transações de cobrança.

## DirectAdmin

1. ADM → Integrações → DirectAdmin. Origem HTTPS na porta 2222 ou 443, usuário administrador/revendedor, **Login Key dedicada** no campo token e prefixo exclusivo de duas letras. Restrinja a chave ao IP de saída e operações necessárias. Não use senha principal do administrador.
2. O servidor precisa expor a API atual `GET /api/users/{username}/config` e os comandos documentados `CMD_API_ACCOUNT_USER`, `CMD_API_SELECT_USERS`. A primeira usa o contrato JSON atual; comandos antigos usam POST form com `json=yes`. Não é compatibilidade declarada com toda versão antiga ou fork.
3. No produto, selecione a integração e informe o JSON:

```json
{"domain_suffix":"clientes.exemplo.com","plan":"basic","ip":"203.0.113.10"}
```

Substitua por domínio-base sob seu controle, pacote existente do revendedor e IP livre/compartilhado permitido para ele. `203.0.113.10` é somente exemplo de documentação.

O login é prefixo + ID local em base36 (8 caracteres), e o domínio é login.domínio-base. API Basic usa usuário e Login Key somente no header. Criação gera senha própria e `notify=no`; cliente consulta o acesso inicial no LagosPanel com senha/2FA. O plano controla quotas, sem tradução de opções extras. Leitura compara username, creator, domain, email, package, ip, `userType=user` e booleano suspended. Conta administrativa ou identidade divergente é recusada.

Suspensão usa `dosuspend`, reativação `dounsuspend`, **nunca toggle**. Exclusão usa exclusivamente `select0` do contrato com `delete=yes`/`confirmed=Confirm`, após confirmação explícita no ADM. Não remove outros usuários. Acesso inicial não acompanha alterações posteriores da senha. Ainda não há SSO DirectAdmin, upgrade, reset remoto, gestão DNS/mail/backups ou métricas individuais pelo LagosPanel.

## Plesk gerenciado

1. Crie previamente um proprietário autorizado e um plano no Plesk. O modo desta versão vende hospedagem **gerenciada**, não cria automaticamente clientes Plesk.
2. ADM → Integrações → Plesk. Origem HTTPS 8443 ou 443, prefixo exclusivo de duas letras e **secret key XML API** do administrador no campo token. Esta chave usa header `KEY` e restrição de IP no Plesk. Não confundir com outras credenciais de REST/Partner API.
3. JSON do produto:

```json
{"domain_suffix":"clientes.exemplo.com","plan_guid":"01234567-89ab-4cde-8123-456789abcdef","owner_id":12,"ip":"203.0.113.10"}
```

Todos os valores são exemplos; use GUID real do plano, ID do proprietário e IP de hospedagem disponíveis no servidor. Não configure proprietário de terceiros sem autorização. As assinaturas têm usuário de sistema/FTP próprio, mas continuam sob o proprietário informado.

XML API `1.6.9.1`, endpoint `/enterprise/control/agent.php`, operador `webspace`: `add`, `get`, `set`, `del`. Criação inclui `external-id` UUID exclusivo, domínio, proprietário, IP, plano e login/senha FTP próprios. Consulta compara esses identificadores, tipo de hosting e plano. Plano adicional, identidade divergente ou estrutura ambígua bloqueiam a operação, em vez de alterar recursos desconhecidos. O modo não audita todas as quotas personalizadas do plano.

Estado 0 é ativo; 16 é suspensão administrativa gerenciada. Outros estados, incluindo suspensão por proprietário/revendedor ou flags combinadas, não são automaticamente removidos. Mutação usa ID numérico previamente conferido. Parser rejeita DTD/entities, NUL, XML inválido, resultados múltiplos e respostas excessivas; não expande entidades externas. Só erro de objeto ausente 1013 na consulta é ausência, não erro de autenticação.

O cliente recebe **somente credenciais de publicação FTP/FTPS da própria assinatura**, após senha/2FA, em página privada/no-store. Use FTPS com TLS e certificado válido, configurado no provedor. Senha de publicação não dá acesso ao painel Plesk. Não expomos a secret key, login do proprietário ou sessão administrativa. SSO/criação automática de clientes Plesk, upgrades, DNS/mail/backups e gestão avançada continuam fora deste recorte.

## Operação e falhas

Habilite `NATIVE_PROVISIONING_ENABLED=true`, confirme a integração no ADM e mantenha worker/cron. Criação/reativação exige fatura paga. Checkout congela destino, conector, identidade e plano. Mudança posterior do produto não altera serviço vendido. Opções não mapeadas são rejeitadas.

Antes de cada ação, consulta identidade e estado; depois, confirma resultado. `sent_at` é gravado com token de execução antes da mutação. Jobs duplicados não repetem a operação. Timeout vira revisão, nunca sucesso presumido nem retry automático. ADM → Operações permite consultar, confirmar resultado já aplicado ou autorizar nova tentativa após conferência manual. A nova tentativa exige estado compatível e certeza operacional de ausência de execução remota em andamento; não é exactly-once distribuído.

Conta/assinatura preexistente não é adotada automaticamente. Namespace/prefixo deve ser exclusivo entre instalações que compartilham provedor. Encerramento é destrutivo: faça backup e confira a confirmação do ADM. Após encerramento confirmado, a senha inicial local é apagada. Snapshots, IDs, auditoria e credenciais não substituem backups ou políticas de retenção.

## Fontes oficiais consultadas

- https://docs.directadmin.com/developer/api/ — Basic Auth, Login Keys, impersonação e API atual.
- https://demo.directadmin.com:2222/static/swagger.json — contrato efetivo de user config, tipos e erro `NOT_FOUND` (catálogo público consultado sem autenticação).
- https://docs.directadmin.com/developer/api/legacy-api.html — criação/exclusão por pacote, parâmetros e JSON.
- https://docs.directadmin.com/changelog/version-1.31.0.html — `dosuspend`/`dounsuspend`, sem toggle.
- https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-secret-keys.37121/ — header KEY e IP.
- https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-subscriptions/creating-a-subscription.33892/ — criação, hosting, proprietário e plano.
- https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-subscriptions/getting-information-about-subscriptions.33899/ — datasets e filtros.
- https://docs.plesk.com/en-US/obsidian/api-rpc/about-xml-api/reference/managing-subscriptions/subscription-settings/subscription-statuses-and-associated-plans.66383/ — plano e assinaturas.
- https://github.com/plesk/api-schemas/tree/cb700f4e6fceb8702840f68e54c3a78f0eeb7f4c/1.6.9.1 — diagramas oficiais; estruturas revisadas, sem alegar validação automatizada XSD. Resumo em `HOSTING-API-REFERENCES.json`.

Testes PHP e concorrentes utilizam APIs simuladas com o contrato documentado. Não houve criação autenticada de contas em DirectAdmin/Plesk reais. Chave válida é necessária, mas versão compatível, permissões, licença/capacidade, pacote, proprietário, IP, DNS/TLS e worker também precisam estar corretos.
