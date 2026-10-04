# Extensões de servidor do Paymenter no LagosPanel

## Regra de portabilidade

O LagosPanel não deve copiar o Paymenter inteiro. Cada extensão será adaptada ao contrato nativo de `Connector`, `Product`, `Service` e `Operation`, preservando o aviso MIT quando a extensão estiver coberta pela licença MIT do Paymenter.

A extensão **Proxmox atual é paga/licenciada**. O código legado público é MIT, está depreciado e não deve ser tratado como equivalente à extensão atual paga.

## Extensões mapeadas

| Extensão | Credencial principal | Hooks | Estado no LagosPanel |
|---|---|---|---|
| cPanel/WHM | Host HTTPS, usuário WHM, API key | create, suspend, unsuspend, terminate, upgrade, login temporário | Adaptador Paymenter em portabilidade |
| Convoy | Host, API key Bearer | create, suspend, unsuspend, terminate, upgrade, SSO | Adaptador Paymenter em portabilidade |
| DirectAdmin | Host, usuário e senha/API Basic Auth | create, suspend, unsuspend, terminate, SSO | Adaptador Paymenter em portabilidade |
| Enhance | Host, API key SuperAdmin, organization ID | create, suspend, unsuspend, terminate, upgrade, SSO | Adaptador Paymenter em portabilidade |
| Plesk | Host HTTPS, usuário root/admin e senha | create, suspend, unsuspend, terminate | Adaptador Paymenter em portabilidade; upgrade não existe no addon |
| Proxmox | API token, cluster, node/storage | depende da extensão paga atual | Não copiar sem licença; adapter próprio após licença |
| Pterodactyl | URL e Application API key | create, suspend, unsuspend, terminate, upgrade, painel | Adaptador Paymenter em portabilidade |
| VirtFusion | Host e API key Bearer | create, suspend, unsuspend, terminate, SSO | Adaptador Paymenter em portabilidade; upgrade não existe no addon |
| Virtualizor | IP/host, API key, Key Pass, portas | create, suspend, unsuspend, terminate, upgrade, SSO | Adaptador Paymenter em portabilidade |

## Contrato nativo planejado

Cada driver do LagosPanel deverá declarar:

- `testConnection()`;
- `productConfig()`;
- `checkoutConfig()`;
- `create(Service $service, array $settings, array $properties)`;
- `suspend(Service $service)`;
- `unsuspend(Service $service)`;
- `terminate(Service $service)`;
- `upgrade(Service $service, array $settings, array $properties)` quando suportado;
- `actions(Service $service)`;
- `reconcile(Service $service)`.

Os jobs devem ser idempotentes, registrar `Operation`, salvar o identificador externo antes de concluir, ocultar tokens/senhas dos logs e deixar o serviço em `needs_reconciliation` quando o provedor responder de forma incerta.

## Fontes oficiais consultadas

- Paymenter — Servers: https://paymenter.org/docs/guides/servers/
- Paymenter — Products: https://paymenter.org/docs/guides/products/
- Paymenter — Server extensions: https://paymenter.org/development/extensions/server
- Paymenter — cPanel: https://paymenter.org/docs/extensions/cpanel
- Paymenter — Convoy: https://paymenter.org/docs/extensions/convoy
- Paymenter — DirectAdmin: https://paymenter.org/docs/extensions/directadmin
- Paymenter — Enhance: https://paymenter.org/docs/extensions/enhance
- Paymenter — Plesk: https://paymenter.org/docs/extensions/plesk
- Paymenter — Proxmox: https://paymenter.org/docs/extensions/proxmox
- Paymenter — Pterodactyl: https://paymenter.org/docs/extensions/pterodactyl
- Paymenter — VirtFusion: https://paymenter.org/docs/extensions/virtfusion
- Paymenter — Virtualizor: https://paymenter.org/docs/extensions/virtualizor
- Código oficial: https://github.com/Paymenter/Paymenter
- Licença do código MIT: https://github.com/Paymenter/Paymenter/blob/master/LICENSE

## Credenciais

As credenciais serão inseridas somente quando as contas de homologação estiverem prontas. Nenhuma chave, senha, token ou certificado deve entrar no Git, no checkout ou nos logs do LagosPanel.
