<?php

namespace App\Support;

final class Totp
{
    public static function secret(): string
    {
        $bits = '';
        foreach (str_split(random_bytes(20)) as $b) {
            $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[bindec($chunk)];
        }

        return $out;
    }

    public static function code(string $secret, int $step): string
    {
        $bits = '';
        foreach (str_split($secret) as $c) {
            $bits .= str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $c)), 5, '0', STR_PAD_LEFT);
        }
        $key = '';
        foreach (str_split($bits, 8) as $b) {
            if (strlen($b) === 8) {
                $key .= chr(bindec($b));
            }
        }
        $hash = hash_hmac('sha1', pack('N2', 0, $step), $key, true);
        $o = ord($hash[19]) & 15;
        $num = unpack('N', substr($hash, $o, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function step(string $secret, string $code, int $last = -1): ?int
    {
        if (! preg_match('/^\d{6}$/D', $code)) {
            return null;
        }
        $now = intdiv(now()->timestamp, 30);
        foreach ([$now, $now - 1, $now + 1] as $step) {
            if ($step > $last && hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }
}
