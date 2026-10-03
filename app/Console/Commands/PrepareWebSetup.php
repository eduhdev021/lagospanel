<?php

namespace App\Console\Commands;

use App\Services\WebInstaller;
use Illuminate\Console\Command;

class PrepareWebSetup extends Command
{
    protected $signature = 'lagos:setup';

    protected $description = 'Prepara instalação web por 30 minutos com chave de uso único; recusa banco com usuários.';

    public function handle(WebInstaller $installer): int
    {
        try {
            $token = $installer->prepare();
        } catch (\Throwable) {
            $this->error('Não foi possível preparar. Confira banco acessível/sem usuários, ausência de installed.lock, .env e permissões. Não use em instalação existente.');

            return 1;
        }
        $this->info('Acesse /instalar no navegador pelo HTTPS do seu servidor. Chave válida por 30 minutos (não compartilhe):');
        $this->line($token);
        $this->warn('A chave não deve ir na URL. Ao terminar, o instalador será bloqueado. Banco, site e administrador são definidos no navegador.');

        return 0;
    }
}
