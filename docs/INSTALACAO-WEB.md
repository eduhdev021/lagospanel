# Instalação pelo navegador — 1.1.0

## Servidor antes do assistente

PHP 8.2+, Composer 2 e extensões do README. Domínio/HTTPS já preparados. DocumentRoot deve ser **somente `public/`**. Ajuste o usuário do PHP e permissões de `storage`, `bootstrap/cache`, banco SQLite e raiz/.env durante a instalação; a substituição atômica de `.env` precisa poder criar/renomear arquivo no diretório pai. Após concluir, restrinja a escrita na raiz conforme sua operação. Nunca exponha `.env`, `.cache`, banco, logs ou storage privado.

```bash
cp .env.example .env
# Defina APP_URL, APP_ENV=production, APP_DEBUG=false e cookies seguros.
bash scripts/prepare-web.sh
```

Não execute `scripts/install.sh` primeiro: a via CLI migra diretamente, enquanto o assistente exige banco vazio. Se já usou a CLI, crie o administrador por `php artisan lagos:admin EMAIL`; não apague um banco para contornar a proteção.

`prepare-web.sh` instala dependências e chama `php artisan lagos:setup`. O comando preserva APP_KEY existente, gera uma se ausente e prepara sessões/cache em arquivos. Mostra chave privada com validade de 30 minutos; somente SHA-256 é armazenado. Proteja o terminal e não publique a chave em URL, screenshot ou chamado.

## Pelo navegador

1. Acesse `https://seu-dominio/instalar`. Sem preparação CLI a rota responde 404. Produção recusa HTTP.
2. Informe a chave. CSRF, throttling, sessão regenerada e autorização ligada à chave protegem o fluxo.
3. Confira requisitos, nome e origem HTTPS, sem caminho/query. Selecione SQLite no caminho privado fixo ou MySQL com banco vazio/exclusivo e credenciais.
4. Crie o primeiro administrador (senha com pelo menos 12 caracteres, letras/números), confira o aviso de backup e conclua.
5. A aplicação migra o esquema e cria administrador, configuração e auditoria. Não semeia contas/produtos fictícios. O e-mail inicial é marcado verificado porque o operador controla a chave de instalação; isso **não comprova entrega de e-mail**.
6. `storage/app/private/installed.lock` bloqueia novas instalações; a chave é removida, a sessão invalidada e o login é manual. Ative 2FA no perfil para acessar o ADM em produção.

Configure SMTP e integrações no ADM, worker `queue:work --tries=3 --timeout=75` e cron `schedule:run` a cada minuto. O assistente não instala serviços do sistema, DNS, certificados, tarefas de supervisor ou jogos/painéis remotos.

## Expiração, falhas e atualização

- Durante a preparação, demais rotas respondem 503, mesmo se a chave expirar. Reexecute `php artisan lagos:setup` para rearmar; isso invalida a autorização anterior.
- A identidade do banco de uma tentativa parcial é preservada ao rearmar. Falhas de migration podem ser retomadas no mesmo banco exclusivo depois de corrigir a causa. Banco desconhecido não vazio ou com usuários é recusado, sem limpeza automática.
- Há mutex de arquivo para serializar instalação no mesmo filesystem. Não é coordenação distribuída entre nós com storage separado.
- Não há transação única entre banco e filesystem: `.env` é escrito atomicamente e restaurado em falha pré-commit conhecida. Falha após commit, perda de conexão no commit ou erro no arquivo de bloqueio exige revisão do operador. Verifique banco, `.env`, APP_KEY e estado; **não remova bloqueios nem recrie administrador às cegas**.
- Guarde backup seguro de **banco + APP_KEY + arquivos privados**. Alterar/perder APP_KEY pode tornar dados criptografados ilegíveis. Não rode key:generate em atualização.
- Atualização de instalação independente existente: backup, deploy do código, dependências do lockfile, `php artisan migrate --force`, limpeza/recriação dos caches usados e restart dos workers. Não use o instalador para atualização.
- Dados do núcleo anterior e importação WHMCS/Paymenter não são migrados automaticamente. Publicar a nova árvore no mesmo GitHub não realiza migração de dados.

## Verificação executada

Navegador real + SQLite em aplicação extraída separadamente: autorização, CSRF, mobile, instalação completa, bloqueio e login/sessão em banco. Também testados banco desconhecido preservado, usuários existentes, migration que falha após as migrations versionadas, rearmamento, recuperação e conclusão duplicada. PHPUnit cobre isolamento `:memory:`, chave preservada e HTTPS em produção. **MySQL/MariaDB, TLS real, SMTP externo e deploy de produção ainda não homologados.**
