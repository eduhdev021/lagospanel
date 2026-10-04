<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class PanelWorkspace
{
    public function head(): string
    {
        if (! is_dir(base_path('.git')) || is_link(base_path('.git'))) {
            throw new RuntimeException('Instalação sem checkout Git regular.');
        }
        $p = new Process(['git', '-c', 'safe.directory='.base_path(), '-c', 'core.hooksPath=/dev/null', '-c', 'core.fsmonitor=false', 'rev-parse', 'HEAD'], base_path(), ['GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null', 'GIT_DIR' => false, 'GIT_WORK_TREE' => false, 'GIT_CONFIG_COUNT' => false]);
        $p->setTimeout(5);
        $p->run();
        $sha = trim($p->getOutput());
        if (! $p->isSuccessful() || ! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
            throw new RuntimeException('Não foi possível ler a instalação Git.');
        }

        return $sha;
    }
}
