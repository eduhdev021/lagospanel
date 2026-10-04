<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class Avatar
{
    public function validateImage(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);
        if (strlen($bytes) > 262144 || ! $info || ! in_array($info['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true) || $info[0] > 1024 || $info[1] > 1024) {
            throw ValidationException::withMessages(['avatar' => 'Use PNG, JPG ou WebP válido, até 256 KiB e 1024 × 1024 pixels.']);
        }

        return ['avatar_content' => base64_encode($bytes), 'avatar_mime' => $info['mime'], 'avatar_updated_at' => now()];
    }

    public function gravatar(string $email): array
    {
        $hash = hash('sha256', mb_strtolower(trim($email)));
        try {
            $r = Http::withoutRedirecting()->connectTimeout(3)->timeout(8)->withOptions(['verify' => true, 'on_headers' => function ($r) {
                if ((int) $r->getHeaderLine('Content-Length') > 262144) {
                    throw new \RuntimeException('Image too large');
                }
            }, 'progress' => function ($total, $received) {
                if ($received > 262144) {
                    throw new \RuntimeException('Image too large');
                }
            }])->get('https://www.gravatar.com/avatar/'.$hash, ['s' => 192, 'd' => '404', 'r' => 'g']);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['avatar' => 'Não foi possível consultar o Gravatar. Tente novamente ou envie uma foto.']);
        }
        if ($r->status() === 404) {
            throw ValidationException::withMessages(['avatar' => 'Nenhuma foto pública no Gravatar para o e-mail desta conta. Confira o e-mail no Gravatar ou envie uma foto.']);
        }
        if (! $r->successful()) {
            throw ValidationException::withMessages(['avatar' => 'O Gravatar não retornou uma imagem disponível.']);
        }

        return $this->validateImage($r->body()) + ['avatar_source' => 'gravatar'];
    }
}
