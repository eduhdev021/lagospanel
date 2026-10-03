<?php

namespace App\Services;

use Dotenv\Dotenv;

final class EnvironmentFile
{
    public static function replace(string $text, array $values): string
    {
        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/D', $key) || preg_match('/[\x00-\x1f\x7f]/', (string) $value)) {
                throw new \RuntimeException('Valor de ambiente inválido.');
            }
            $encoded = '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], (string) $value).'"';
            $line = $key.'='.$encoded;
            if (preg_match('/^'.preg_quote($key, '/').'=/m', $text)) {
                $text = preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn () => $line, $text);
            } else {
                $text = rtrim($text)."\n".$line."\n";
            }
        }
        $parsed = Dotenv::parse($text);
        foreach ($values as $key => $value) {
            if (($parsed[$key] ?? null) !== (string) $value) {
                throw new \RuntimeException('Não foi possível preservar o valor no arquivo de ambiente.');
            }
        }

        return $text;
    }

    public static function write(string $path, string $contents): void
    {
        $temp = $path.'.tmp-'.bin2hex(random_bytes(8));
        $h = fopen($temp, 'x');
        if (! $h) {
            throw new \RuntimeException('Arquivo temporário indisponível.');
        }chmod($temp, 0600);
        try {
            if (fwrite($h, $contents) !== strlen($contents)) {
                throw new \RuntimeException('Gravação incompleta.');
            }fflush($h);
            fclose($h);
            $h = null;
            if (! rename($temp, $path)) {
                throw new \RuntimeException('Não foi possível salvar configuração.');
            }
        } finally {
            if (is_resource($h)) {
                fclose($h);
            }if (is_file($temp)) {
                unlink($temp);
            }
        }
    }
}
