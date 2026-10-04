<?php

namespace App\Console\Commands;

use App\Services\AiDiagnostics;
use Illuminate\Console\Command;

class AiStatus extends Command
{
    protected $signature = 'lagos:ai-status';

    protected $description = 'Diagnóstico local da IA e fila sem revelar tokens nem mensagens.';

    public function handle(AiDiagnostics $diagnostics): int
    {
        $this->line(json_encode($diagnostics->report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->comment('Nenhuma chamada ao provedor foi feita. Worker recente não garante resposta do modelo.');

        return 0;
    }
}
