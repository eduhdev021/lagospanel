# LagosPanel 1.1.0 — contas Pterodactyl pelo ADM

## Implementado

- Criação de usuário remoto em ADM → Integrações → Contas Pterodactyl, com confirmação explícita do operador.
- Cliente precisa de e-mail verificado; chave com leitura/escrita de usuários, integração ativa e chave global de chamadas nativas habilitada.
- Usuário remoto sempre não administrador. Nome/e-mail são enviados; senha do LagosPanel nunca é enviada. Pterodactyl cuida da definição de senha/convite; SMTP remoto precisa funcionar.
- Vínculo só é salvo após conferir identidade por external_id e por ID remoto. A contratação existente passa a funcionar com esse vínculo, sem preenchimento manual do ID.
- Controle persistente por cliente/integração, trava concorrente e identificador de execução. Após envio, somente consultas: falha ou timeout não repetem o POST de criação.
- Botão de conferência para resultados incertos; proteção contra conta privilegiada, identidade trocada, vínculo ocupado, integração pausada e execução substituída.
- Vínculo manual continua disponível. Visual/arquivos-base preservados.

## Verificação desta revisão

- 268 testes PHP, 1288 asserções (19 novos testes).
- 17 cenários concorrentes com até 20 processos; novo cenário confirmou um único POST/vínculo para 20 solicitações.
- 12 novas verificações Chromium: formulário HTTP real, CSRF, criação, duplicação, timeout, conferência e mobile. Provedor simulado via Http::fake, sem interceptar os POSTs do navegador.
- 15 verificações de instalação pelo navegador e seis guardas do instalador repetidas numa cópia extraída do ZIP; 14 migrations concluídas em SQLite.
- Testes anteriores de navegador permanecem como evidência histórica; não alegamos que todos foram reexecutados.
- Nenhuma conta criada em Pterodactyl real, nenhuma mensagem SMTP externa nem deploy em servidor real.

## Atualizar

Backup de banco + APP_KEY + arquivos privados, dependências pelo lockfile, `php artisan migrate --force`, atualização dos caches utilizados e restart dos workers. Nova migration cria somente `pterodactyl_account_requests`, com vínculos existentes preservados. Não recrie APP_KEY e não execute o instalador sobre dados existentes.

## Ainda falta

Criação de conta disparada pelo próprio checkout, energia/console, SSO, backups, reinstalação e upgrades continuam pendentes, assim como as demais frentes de `ESCOPO.md`. Esta entrega fecha a criação de contas pelo ADM, não toda a integração Pterodactyl.
