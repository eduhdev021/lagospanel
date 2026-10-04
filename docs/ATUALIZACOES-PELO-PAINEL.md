# Configurações administrativas e atualização pelo painel

O ADM possui uma central em **Configurações**, com Geral, E-mail, Segurança,
Ambiente e Atualizações, além dos atalhos de integrações, IA, equipe e catálogo.
As permissões existentes continuam válidas. Segurança e Atualizações são
restritas ao administrador principal (`is_admin`), não a qualquer operador.
O tema existente foi preservado.

## 2FA opcional

A política inicial agora é `LAGOS_REQUIRE_ADMIN_2FA=false`. Uma política explicitamente
ativada no `.env` continua respeitada; uma escolha salva no ADM tem precedência.
**Configurações → Segurança** permite alterar a exigência sem editar código,
confirmando a senha atual. Para tornar obrigatório, primeiro cadastre seu próprio
2FA. Quem já possui 2FA pessoal continua usando-o: isso não remove autenticadores,
senhas, permissões ou a confirmação de código para operações sensíveis.

## O que o botão faz

1. Consulta exclusivamente o repositório público `eduhdev021/lagospanel`, `main`.
2. Exige sucesso do último CI push/main daquele SHA no workflow `tests.yml`.
3. Mostra comparação de commits; consulta e aprovação expiram após 15 minutos.
4. Um worker independente, não-root, revalida o commit, CI, administrador,
   Git limpo, ancestralidade e permissões. Nunca usa `reset --hard`.
5. Prepara Composer em worktree separado, sem plugins/scripts e sem dependências de desenvolvimento.
6. Pausa a fila database, entra em manutenção 503, aguarda requisições comuns e jobs reservados.
7. Faz backup consistente do SQLite, `.env`, fonte Git e `storage/app`, com hashes.
8. Aplica fast-forward, troca vendor, executa migrations e recompila caches.
9. Confere `.env` intacto e SHA, libera manutenção e registra o resultado.

**Main é desenvolvimento, não uma release estável.** A validação automática de
caches não equivale a homologação HTTP, SMTP ou dos provedores reais.
Não há garantia de recuperação automática. Backup local não substitui cópia externa.

## Habilitação inicial pelo operador (uma vez)

A versão que contém este recurso precisa ser instalada primeiro por implantação
manual revisada. Instalações por ZIP não possuem Git e não podem usar o botão.
Se houver alterações do atendimento anterior (middleware/config de 2FA), preserve-as
em backup e revise o diff antes de integrar a nova versão. Não descarte arquivos locais.

Requisitos desta primeira implementação:

- `/var/www/lagospanel`, checkout Git regular, Python 3.9+, Git, PHP 8.4 e Composer.
- SQLite físico dentro do checkout, `QUEUE_CONNECTION=database`, manutenção `file`.
- Fila permanente **sem `--force`** e scheduler configurados; aguarde tarefas longas
  e evite cron/importações independentes durante a atualização. A espera HTTP é
  de 30 segundos, não uma prova de ausência de todas as requisições em voo.
- Usuário dedicado `lagospanel-deploy`, sem sudo, grupo `www-data` (adapte se seu
  pool FPM usa outro grupo). Nunca execute o atualizador como root ou crie uma
  rota web que rode comandos como root.
- Código, `.git`, `.cache` e `bootstrap/cache` pertencentes a esse usuário, **sem
  escrita de grupo/outros**; diretórios normalmente 0750, arquivos 0640,
  executáveis preservados. `.env` deve ser regular, protegido e legível pelo app.
- PHP-FPM só precisa de escrita em `storage` e no diretório/arquivo SQLite,
  inclusive arquivos WAL/SHM. Garanta escrita compartilhada ao usuário de
  implantação nesses locais, usando grupo/setgid ou ACL específica.
- Não altere permissões de `/var/www` inteiro, Paymenter, outros sites ou MariaDB.
- Espaço para dependências em duplicidade, banco, uploads e fonte. O bloqueio
  mínimo de 512 MiB **não calcula** todo o espaço necessário aos seus dados.

Não há um comando universal de `chown -R`: examine a propriedade atual e os
processos deste painel antes de preparar essa separação. O worker recusa pastas
centrais com escrita de grupo para não permitir ao PHP substituir código do atualizador.

Após preparar permissões e backup externo, configure no `.env` do LagosPanel:

```dotenv
PANEL_UPDATES_ENABLED=true
PANEL_UPDATES_COMPOSER=/usr/local/bin/composer
APP_MAINTENANCE_DRIVER=file
QUEUE_CONNECTION=database
```

Execute `php8.4 artisan config:cache` como o usuário de implantação, não como root.
Confira o caminho do PHP/Composer nos exemplos antes de instalar as unidades:

```sh
# Como operador root, somente depois de preparar o usuário/permissões acima:
cp /var/www/lagospanel/examples/systemd/lagospanel-updater.{service,timer} /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now lagospanel-updater.timer
systemctl start lagospanel-updater.service
systemctl status lagospanel-updater.timer --no-pager
journalctl -u lagospanel-updater.service -n 40 --no-pager
```

O serviço é oneshot e pode aparecer inativo entre verificações: o timer o inicia
novamente. No ADM, o heartbeat deve aparecer pronto. Depois, consulte o commit e
use **Atualizar painel para este commit**, com senha/2FA pessoal quando cadastrado.
Nenhum PAT do GitHub é necessário. O site fica em manutenção durante a aplicação;
a página consulta o estado e volta a funcionar quando o worker termina.

## Falhas e recuperação

Antes da alteração da fonte, falhas normalmente liberam **somente a manutenção
criada por este worker**. Depois de iniciar a aplicação, falhas deixam o painel
em manutenção. Interrupção por energia/timeout pode deixar registro `running`:
isso bloqueia novas atualizações até intervenção. Nunca force outra execução.

1. Pare o timer (`systemctl stop lagospanel-updater.timer`). Verifique se o serviço
   ainda executa: não faça recuperação concorrente.
2. Preserve o diretório privado `.cache/panel-updater/update-<id>-<uuid>/`:
   `journal.json`, `process.log`, `manifest.json`, `.env`, `database.sqlite`,
   `source.tar`, `storage-app.tar.gz`, possíveis caches e `vendor.previous`.
   Logs/backups contêm dados sensíveis; não publique ou coloque em `public/`.
3. Confira `git status`, HEAD, fase e hashes dos backups. Capture também um backup
   consistente **do banco atual** antes de qualquer tentativa de restauração.
4. Prefira corrigir a causa e completar a implantação do mesmo commit. Migrações
   podem ter sido parcialmente aplicadas; consulte `migrate:status`. Não faça
   downgrade/rollback automático nem copie SQLite sobre banco aberto.
5. Se restauração for inevitável, faça-a em instalação isolada e valide código,
   dependências, banco, uploads e APP_KEY do mesmo ponto. Só troque a instalação
   com todos os escritores parados e plano para reconciliar dados posteriores.
6. Após validar a recuperação, o operador pode executar `php8.4 artisan up` e
   remover **somente** `storage/framework/panel-update-pause` deste trabalho.
   Reinicie fila e valide HTTP/login/ADM antes de reativar o timer.
7. Caso o registro continue `running`, marque-o explicitamente `failed` após
   confirmar ausência do processo. Via `artisan tinker`, como operador autorizado:
   `App\Models\PanelUpdate::whereKey(ID_REVISADO)->where('status','running')->update(['status'=>'failed','phase'=>'manual_recovery','finished_at'=>now()]);`
   Troque `ID_REVISADO` pelo número correto; não apague o histórico. Registre a
   intervenção na operação do servidor e faça uma nova consulta no ADM.

Backups não são removidos automaticamente. Defina retenção/cópia externa e
monitore disco. Não use este atualizador para MySQL, Redis queue, manutenção cache,
containers imutáveis ou deploy distribuído; nesses casos mantenha implantação externa.
