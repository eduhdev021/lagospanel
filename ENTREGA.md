# LagosPanel 1.2.0 — automação e acesso

Pacote local implementado e validado. Última publicação pública confirmada: 1.1.0. Sem afirmação de push/CI novo, deploy ou paridade completa com WHMCS/Paymenter.

## Implementado

- Conta Pterodactyl após pagamento, por opção `"auto_account":true`. Checkout sem HTTP; jobs separados para conta/servidor; uma conta compartilhada nas compras simultâneas do cliente.
- Retomada administrativa com permissões, justificativa e proteção contra reenvio de criação já enviada.
- Ligar/parar/reiniciar pela Client API do cliente: rejeita chave administrativa/conta divergente; senha/2FA, idempotência persistente e nenhum retry automático. Chave por solicitação, não armazenada.
- SSO temporário cPanel: senha/2FA, conferência de conta e origem restrita; sessão não persistida.
- Tema/layout-base preservados. Guia: `docs/AUTOMACAO-E-ACESSO-1.2.md`.

## Validação

- 309 testes PHP / 1690 asserções: 41 casos novos, incluindo datasets.
- 18 cenários concorrentes, até 20 processos. Novo cenário: 20 serviços pagos compartilham uma conta Pterodactyl e retomam provisionamento sem duplicar POST.
- 13 verificações Chromium de controles/SSO por HTTP real, com CSRF e APIs externas simuladas; 15 do instalador e seis guardas numa cópia extraída do pacote (15 migrations).
- Composer strict validate/audit aprovados; zero avisos de vulnerabilidade na consulta.
- Provedores simulados, não homologados com contas Pterodactyl/WHM reais. Testes anteriores de navegador são históricos, não contados como novos.

## Atualizar

Backup de banco, APP_KEY e arquivos privados; dependências do lockfile; `php artisan migrate --force`; refaça caches utilizados e reinicie workers. Nova migration cria `pterodactyl_controls` (15 migrations), sem alterar vínculos existentes. Não recrie APP_KEY nem execute instalador sobre dados existentes.

## Ainda não está completo

Console/SSO Pterodactyl, backups/reinstalação/upgrades, cobertura completa dos demais painéis, VPS/cloud, registradores, financeiro avançado, subcontas, extensões/importadores e homologação operacional continuam pendentes. Veja `docs/ESCOPO.md` e matrizes de paridade.
