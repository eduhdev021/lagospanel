# LagosPanel 1.3.0 — DirectAdmin e Plesk

## Implementado nesta versão

- DirectAdmin nativo: contas individuais por pacote/IP, criação após pagamento, suspensão/reativação sem toggle, encerramento, conciliação e acesso inicial privado com senha/2FA.
- Plesk XML API: criação e ciclo de assinaturas gerenciadas sob proprietário existente, plano por GUID, identidade externa exclusiva e credenciais FTP/FTPS privadas da assinatura. Sem compartilhar acesso administrativo.
- Cadastro/configuração no ADM, planos congelados no pedido, fila, conferência antes/depois, confirmação de exclusão e retomada controlada pelo operador.
- Preserva os recursos da 1.2.0: conta Pterodactyl após pagamento, energia pela Client API e SSO cPanel. Tema/assets/layout-base mantidos.

## Verificação

- **341 testes PHP, 1832 asserções**, incluindo 32 casos novos dos painéis.
- **20 cenários concorrentes**, com até 20 processos. DirectAdmin e Plesk: uma criação para 20 entregas duplicadas do job.
- Regressão, compilação/lint de Blade, cache de rotas, PHP lint, Composer validate/audit e integridade do ZIP.
- Provedores simulados a partir dos contratos oficiais; sem contas reais nesses provedores. Relatórios de navegador da 1.2.0 são históricos e não foram reexecutados nesta revisão.

## Configuração e atualização

Leia `docs/DIRECTADMIN-E-PLESK.md`. Token/chave, usuário quando necessário, pacote/GUID, IP e proprietário Plesk precisam corresponder à configuração real. Habilite chamadas nativas e mantenha worker/cron. Sem nova migration nesta versão; continuam 15 no total.

Antes de atualizar: backup de banco, APP_KEY e arquivos privados; dependências pelo lockfile; `php artisan migrate --force`; refaça os caches usados e reinicie workers. Não recrie APP_KEY nem rode instalador sobre dados existentes.

## Limites explícitos

Esta versão não encerra toda a meta WHMCS/Paymenter. Ainda faltam criação automática de clientes e SSO Plesk, SSO DirectAdmin, upgrades, gestão avançada DNS/mail/backups, console/SSO/reinstalação Pterodactyl, registradores, VPS/cloud e outras frentes da matriz de paridade. Ela entrega dois drivers funcionais integrados, não apenas formulários ou promessas de compatibilidade universal.
