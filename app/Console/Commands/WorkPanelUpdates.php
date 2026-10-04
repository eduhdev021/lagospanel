<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class WorkPanelUpdates extends Command
{
    protected $signature = 'lagos:updates-work';

    protected $description = 'Executa uma rodada do atualizador; somente usuário de implantação não-root.';

    public function handle(): int
    {
        if (! config('panel_updates.enabled')) {
            $this->error('PANEL_UPDATES_ENABLED está desabilitado.');

            return 1;
        }
        if (! function_exists('posix_geteuid') || posix_geteuid() === 0) {
            $this->error('Execute como usuário de implantação não-root, nunca como root ou via sudo irrestrito no PHP-FPM.');

            return 1;
        }
        if (config('database.default') !== 'sqlite' || config('queue.default') !== 'database' || config('app.maintenance.driver', 'file') !== 'file') {
            $this->error('Atualização automática disponível somente com SQLite e fila database nesta implementação.');

            return 1;
        }
        $rows = DB::select('PRAGMA database_list');
        $database = collect($rows)->first(fn ($r) => $r->name === 'main')?->file;
        if (! $database || ! is_file($database)) {
            $this->error('Banco SQLite físico não encontrado.');

            return 1;
        }
        $process = new Process(['python3', base_path('scripts/update_runner.py'), '--root', base_path(), '--database', $database, '--php', PHP_BINARY, '--composer', config('panel_updates.composer')], base_path());
        $process->setTimeout(1800);
        $process->run(function ($type, $data) {
            $this->output->write($data);
        });

        return $process->getExitCode() ?? 1;
    }
}
