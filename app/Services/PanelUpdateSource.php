<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PanelUpdateSource
{
    public const REPOSITORY = 'eduhdev021/lagospanel';

    public const URL = 'https://github.com/eduhdev021/lagospanel.git';

    private function get(string $path): array
    {
        $r = Http::retry(2, 250)->withoutRedirecting()->connectTimeout(5)->timeout(15)->withOptions(['verify' => true, 'on_headers' => function ($r) {
            if ((int) $r->getHeaderLine('Content-Length') > 2097152) {
                throw new RuntimeException('Resposta excessiva.');
            }
        }, 'progress' => function ($total, $received) {
            if ($received > 2097152) {
                throw new RuntimeException('Resposta excessiva.');
            }
        }])->withHeaders(['Accept' => 'application/vnd.github+json', 'User-Agent' => 'LagosPanel-Updater/1'])->get('https://api.github.com/repos/'.self::REPOSITORY.$path);
        if (! $r->successful()) {
            throw new RuntimeException('GitHub respondeu HTTP '.$r->status().'.');
        }
        if (strlen($r->body()) > 2097152 || ! is_array($r->json())) {
            throw new RuntimeException('GitHub retornou uma resposta inválida.');
        }

        return $r->json();
    }

    public function latest(): string
    {
        $repo = $this->get('');
        if (($repo['full_name'] ?? '') !== self::REPOSITORY || ($repo['private'] ?? true) !== false || ($repo['archived'] ?? true) !== false) {
            throw new RuntimeException('Repositório inválido.');
        }
        $sha = $this->get('/commits/main')['sha'] ?? '';
        if (! is_string($sha) || ! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
            throw new RuntimeException('Commit inválido.');
        }
        $runs = $this->get('/actions/runs?head_sha='.$sha.'&per_page=100')['workflow_runs'] ?? [];
        $runs = array_filter($runs, fn ($r) => is_array($r) && ($r['head_sha'] ?? '') === $sha && ($r['head_branch'] ?? '') === 'main' && ($r['event'] ?? '') === 'push' && ($r['path'] ?? '') === '.github/workflows/tests.yml' && ($r['head_repository']['full_name'] ?? '') === self::REPOSITORY);
        usort($runs, fn ($a, $b) => ($b['id'] ?? 0) <=> ($a['id'] ?? 0));
        $run = $runs[0] ?? [];
        if (($run['status'] ?? '') !== 'completed' || ($run['conclusion'] ?? '') !== 'success') {
            throw new RuntimeException('A main ainda não tem CI aprovado; status='.($run['status'] ?? 'ausente').', conclusão='.($run['conclusion'] ?? 'ausente').'.');
        }

        return $sha;
    }
}
