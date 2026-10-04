# Credenciais pendentes para homologação

Nenhum segredo deve ser commitado. Os campos podem ser preenchidos pelo painel em **Configurações → Gateways** quando as contas estiverem prontas.

## Pagamentos

| Provedor | Credenciais necessárias | Ambiente recomendado |
|---|---|---|
| Efí Bank | Client ID, Client Secret, certificado P12/PEM, senha do certificado e chave Pix | Homologação primeiro |
| Stripe | Secret key e webhook signing secret | Test mode primeiro |
| Mercado Pago | Access token e credenciais de aplicação | Teste primeiro |
| PayPal | Client ID, Client Secret e webhook ID | Sandbox primeiro |
| Mollie | API key e webhook secret | Test mode primeiro |
| Asaas | API key e webhook token | Sandbox primeiro |
| PagBank | Token/chaves da aplicação e webhook secret | Sandbox primeiro |
| Pagar.me | Secret key, public key e webhook secret | Test mode primeiro |

## Provisionamento

| Integração | Credenciais necessárias |
|---|---|
| cPanel/WHM | Host HTTPS, usuário/API token e pacote de teste |
| Plesk | Host HTTPS, API key e cliente de homologação |
| DirectAdmin | Host HTTPS, usuário/API token e pacote de teste |
| Pterodactyl | URL da Panel, Application API key e Client API key |
| aaPanel | URL HTTPS, app ID e app key |
| Proxmox | Endpoint, token ID e token secret |
| Hetzner/DigitalOcean/AWS | Credenciais restritas, região e limites de teste |

## Domínios e fiscal

| Integração | Credenciais/dados necessários |
|---|---|
| Registro.br/registrador | Conta de revenda, API key e ambiente de teste |
| OpenSRS/ResellerClub/Namecheap | Conta de revenda, API key e IP permitido |
| NFS-e/NF-e | Certificado, credenciais da prefeitura/SEFAZ e município/UF |

## Ordem segura de habilitação

1. Criar contas de sandbox/homologação.
2. Configurar o provedor no painel sem ativar pagamentos reais.
3. Testar criação, consulta, webhook, timeout e replay.
4. Conferir conciliação, ledger e fatura.
5. Ativar produção somente depois do teste completo.

As credenciais devem ser inseridas no painel ou no `.env` do servidor. Nunca enviar chaves, certificados ou senhas em mensagens, issues ou commits.
