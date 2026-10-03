# cPanel / WHM API 1 — alpha.4

## Estado da integração

Driver nativo implementado, não uma chamada ao conector JSON genérico. Criação, consulta, suspensão, reativação e remoção de conta, configuração por produto, snapshot por serviço, senha inicial criptografada e conciliação estão executáveis. **Testes locais com simulação; não houve homologação em servidor WHM real.** O código não cria VMs, registra domínios ou comprova equivalência integral a módulos comerciais.

Referências oficiais consultadas em 03/10/2026:

- https://api.docs.cpanel.net/guides/guide-to-api-authentication/guide-to-api-authentication-api-tokens-in-whm/
- https://api.docs.cpanel.net/specifications/whm.openapi/account-creation/accounts-createacct
- https://api.docs.cpanel.net/specifications/whm.openapi/suspensions/accounts-suspendacct
- https://api.docs.cpanel.net/specifications/whm.openapi/suspensions/accounts-unsuspendacct
- https://api.docs.cpanel.net/specifications/whm.openapi/account-management/accounts-removeacct
- https://api.docs.cpanel.net/specifications/whm.openapi/account-management/accounts-listaccts

A biblioteca pública do fornecedor documenta requisições POST e o método `whm_api` de sua implementação usa POST. O driver foi escrito independentemente, enviando dados no corpo, não senha na URL. [1](https://github.com/CpanelInc/cPanel-PublicAPI/blob/master/lib/cPanel/PublicAPI.pod)

## Configuração

1. Prepare um WHM de homologação autorizado, com certificado TLS válido, pacote real e domínio-base sob seu controle. Não use o servidor de clientes como primeiro teste.
2. Crie token WHM para `root` ou revendedor, com somente os privilégios necessários a `listaccts`, `createacct`, `suspendacct`, `unsuspendacct` e `removeacct`. Verifique os ACLs disponíveis na sua versão; token/reseller e capacidade devem ser homologados.
3. Em **Integrações**, selecione cPanel/WHM, origem HTTPS sem caminho (`https://whm.exemplo.com:2087`, ou 443), usuário, token, prefixo de duas letras minúsculas e URL HTTPS de cPanel para o cliente (2083 ou 443).
4. O prefixo precisa ser exclusivo entre instalações que compartilham o mesmo servidor. O usuário tem oito caracteres: prefixo + ID local em base36, preenchido com zeros. O limite do namespace é `36^6 - 1` IDs; conflito remoto não é adotado nem sobrescrito automaticamente.
5. No produto, selecione a integração, nome exato do pacote WHM e domínio-base. A conta inicial usa `<usuario>.<dominio-base>`. Configure DNS/delegação adequadamente fora do LagosPanel; não há registro de domínio, validação de posse automática nem garantia de emissão de SSL.
6. Habilite a integração com confirmação explícita e `NATIVE_PROVISIONING_ENABLED=true` somente no ambiente preparado. Por padrão, o bloqueio global impede todas as chamadas nativas. **Ele não bloqueia vendas:** mantenha produtos nativos indisponíveis enquanto não estiverem prontos.
7. Rode fila persistente e monitore Operações. Nenhuma ação nativa é executada durante o cadastro da integração/produto.

O pacote WHM determina recursos. Não há mapeamento de opções para memória/disco/limites individuais: checkout com opções selecionadas é bloqueado, evitando cobrar recursos não provisionados. Use um produto por pacote. Snapshots preservam pacote, domínio, usuário, e-mail, origem e identidade WHM; editar produto não muda serviços existentes. Conectores antigos continuam JSON.

A interface permite trocar token e pausar integração, mas não trocar destino/driver/identidade silenciosamente. O driver relê a integração antes de cada requisição. Pausar impede etapas futuras, **não desfaz requisições já recebidas pelo WHM**. Configure egress/firewall/allowlist no host: somente operadores confiáveis devem ter `integrations.manage`; administradores configuram endpoints e podem legitimamente usar redes privadas.

## Contrato implementado

- HTTPS, `Authorization: whm usuario:token`, POST form com `api.version=1`; validação TLS ligada e redirects desligados. cURL obrigatório.
- `listaccts` com busca exata por usuário e campos explícitos. Resposta ausente/malformada não significa conta inexistente. Quando a conta existe, usuário/domínio/proprietário/pacote/e-mail devem coincidir com o snapshot.
- `createacct`: usuário, domínio, pacote, proprietário, contato e senha aleatória forte; `forcedns=0`, `savepkg=0`. Não força substituição de DNS nem cria pacote.
- `suspendacct(user=...)`; `unsuspendacct(user=...)`; `removeacct(username=..., keepdns=0)`.
- Sucesso exige HTTP 2xx e `metadata.result=1`, comando e versão corretos. Depois da mutação, consulta novamente e só atualiza o serviço se o estado for confirmado.
- Limite de 15 segundos por requisição, conexão 5 segundos, resposta de até 1 MiB; a operação pode fazer três requisições. Job: 60 segundos; `retry_after` deve ser maior (90 por padrão).
- Criação/reativação exigem fatura paga. A existência de pagamento não constitui garantia de homologação, disponibilidade ou SLA do provedor.
- Não há retries automáticos. Duplicação local de job não repete uma mutação confirmada. Tokens de execução impedem que resultados de uma execução substituída sobrescrevam o estado local.
- Corpo bruto, razão livre e saída do WHM não são persistidos em erros/auditoria: podem conter senhas. Erros expõem somente mensagens locais controladas. Não habilite logs de corpos HTTP, Telescope ou proxies que registrem credenciais.

## Conciliação e falhas

Timeout, 200 com erro, resposta ambígua ou falha pós-criação deixam a operação em **revisão**, não ativa por suposição. `operations.manage` e `services.manage` são exigidos para conciliar; `operations.view` permite consultar a página.

- **Somente consultar:** consulta o estado, registra observação e mantém o serviço inalterado.
- **Confirmar:** consulta novamente e exige o estado esperado e identidade correspondente. Criação só pode ser confirmada se houve tentativa de envio registrada. Não é importação automática de contas existentes.
- **Repetir:** operador precisa conferir os logs/estado no WHM, verificar que não há worker local nem ação remota pendente, registrar justificativa e confirmar. O sistema exige ausência para recriação e compatibilidade para outras ações; somente enfileira uma tentativa.
- Execução/consulta interrompida pode ser movida para revisão após cinco minutos, sem chamada remota. **Pare/verifique o worker antigo e a operação no WHM antes de autorizar reenvio.** Apenas observar ausência não prova que uma criação remota lenta terminou.

O WHM não oferece a nossa chave de idempotência do conector JSON. Não há garantia de exatamente-uma-vez no provedor, nem fencing remoto; controle local de execução não cancela efeitos de requisições já enviadas. Alterações fora do LagosPanel podem gerar divergência e exigir intervenção. Pagamento durante suspensão por atraso mantém a reativação compensatória.

Encerramento é destrutivo, exige confirmação no formulário e pode remover sites, e-mails, bancos e DNS. Solicitação de cancelamento pelo cliente continua não executando essa exclusão automaticamente. Faça backup externo ao painel antes de habilitar remoções.

## Acesso do cliente

Com serviço ativo e vínculo confirmado, o titular pode revelar a senha **inicial** após informar sua senha do LagosPanel e código TOTP novo, se habilitado. A resposta usa `no-store` e `no-referrer`; o LagosPanel não copia a senha para sessão, flash, e-mail ou API. Notificações/hooks do próprio WHM são externos a essa proteção e precisam ser conferidos na homologação. Revelação é auditada sem gravar a senha. TOTP consumido não pode ser reutilizado.

A senha é criptografada no banco com `APP_KEY`; preserve a chave em backup protegido. Se for alterada no cPanel, a senha inicial armazenada não passa a refletir a nova. Não há sincronização/reset remoto nem SSO nesta versão. Encerramento confirmado apaga a cópia local da senha inicial.

## Teste e homologação pendente

`NativeProvisioningTest`, `browser_native.py`, `FakeWhm.php` e cenários de concorrência usam respostas simuladas, nunca WHM real. O fixture CLI só aceita ambiente LOCAL e origem reservada `whm-fixture.invalid`; a simulação intercepta HTTP sem fallback de rede. Sua página de login usa IP reservado no proxy LOCAL para separar orçamento de autenticação das outras suites, sem afrouxar limites.

Antes de produção, testar com WHM licenciado/isolado: token e ACLs de revendedor, pacotes/quota/capacidade, TLS, criação e acesso real, DNS/SSL, suspensão e reativação, remoção com backup, falhas/timeouts, worker interrompido, dados/contatos divergentes e restauração. Registrar versões/configuração/resultados. Ainda faltam domínio próprio no checkout, SSO, upgrades/troca de pacote, resellers, métricas de uso, sincronização periódica, outros painéis e provedores.
