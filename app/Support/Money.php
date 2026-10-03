<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function parse(string $value): int
    {
        $s = str_replace(',', '.', trim($value));
        if (! preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/D', $s, $m)) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor positivo com até duas casas decimais.']);
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    public static function format(int $value): string
    {
        return 'R$ '.number_format($value / 100, 2, ',', '.');
    }
}
