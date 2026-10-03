<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Provisioning\AaPanelConfig;
use App\Provisioning\ProtocolError;
use Illuminate\Support\Facades\Http;

final class Ollama
{
    public static function origin(string $url): string
    {
        $p = parse_url($url);
        if (config('ai.allow_loopback_http') && ($p['scheme'] ?? '') === 'http' && in_array($p['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true) && ! isset($p['user']) && ! isset($p['pass']) && ! isset($p['query']) && ! isset($p['fragment']) && in_array($p['path'] ?? '', ['', '/'], true) && ($p['port'] ?? 80) > 0 && ($p['port'] ?? 80) <= 65535) {
            return rtrim($url, '/');
        }

        return AaPanelConfig::origin($url);
    }

    private function request(AiSetting $s, string $path, ?array $payload = null): array
    {
        $endpoint = self::origin($s->endpoint);
        $h = Http::acceptJson()->asJson()->withoutRedirecting()->connectTimeout(3)->timeout($payload ? 45 : 10)->withOptions(['verify' => true,
            'on_headers' => function ($r) {
                if ((int) $r->getHeaderLine('Content-Length') > 1048576) {
                    throw new ProtocolError('Resposta Ollama excessiva.');
                }
            },
            'progress' => function ($total, $received) {
                if ($received > 1048576) {
                    throw new ProtocolError('Resposta Ollama excessiva.');
                }
            },
        ]);
        if ($s->token) {
            $h = $h->withToken($s->token);
        }
        try {
            $r = $payload ? $h->post($endpoint.$path, $payload) : $h->get($endpoint.$path);
        } catch (\Throwable) {
            throw new ProtocolError('Ollama indisponível. Confira conexão, chave e limites do provedor.');
        }
        if (! $r->successful() || strlen($r->body()) > 1048576 || ! is_array($d = $r->json()) || isset($d['error'])) {
            throw new ProtocolError('Ollama recusou ou não concluiu a solicitação. Confira chave, modelo e limites.');
        }

        return $d;
    }

    public function models(AiSetting $s): array
    {
        $d = $this->request($s, '/api/tags');
        $list = $d['models'] ?? null;
        if (! is_array($list) || ! array_is_list($list) || count($list) > 500) {
            throw new ProtocolError('Catálogo de modelos inválido ou excessivo.');
        }
        $models = [];
        foreach ($list as $row) {
            $name = $row['name'] ?? null;
            if (! is_string($name) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,179}$/D', $name)) {
                throw new ProtocolError('Nome de modelo inválido.');
            }$models[] = $name;
        }
        $models = array_values(array_unique($models));
        sort($models);

        return $models;
    }

    public function chat(AiSetting $s, array $messages): string
    {
        $d = $this->request($s, '/api/chat', ['model' => $s->model, 'messages' => $messages, 'stream' => false, 'options' => ['num_predict' => 1024, 'num_ctx' => 8192]]);
        if (($d['done'] ?? null) !== true || ($d['message']['role'] ?? '') !== 'assistant' || ! is_string($text = $d['message']['content'] ?? null) || trim($text) === '' || strlen($text) > 32000 || ! empty($d['message']['tool_calls'])) {
            throw new ProtocolError('Resposta Ollama incompleta ou não textual. Nenhuma ferramenta foi executada.');
        }

        return mb_substr($text, 0, 8000);
    }
}
