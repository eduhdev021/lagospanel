<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class Cycle
{
    const LABELS = ['daily' => 'Diário', 'weekly' => 'Semanal', 'biennial' => 'Bienal', 'triennial' => 'Trienal', 'monthly' => 'Mensal', 'quarterly' => 'Trimestral', 'semiannual' => 'Semestral', 'yearly' => 'Anual', 'one_time' => 'Pagamento único'];

    public static function next(CarbonImmutable $date, string $cycle, ?int $anchor = null): ?CarbonImmutable
    {
        if ($cycle === 'one_time') {
            return null;
        }
        if ($cycle === 'daily') {
            return $date->addDay();
        }
        if ($cycle === 'weekly') {
            return $date->addWeek();
        }
        $months = ['biennial' => 24, 'triennial' => 36, 'monthly' => 1, 'quarterly' => 3, 'semiannual' => 6, 'yearly' => 12][$cycle] ?? throw new InvalidArgumentException('Ciclo inválido.');
        $next = $date->startOfMonth()->addMonths($months);

        return $next->day(min($anchor ?? $date->day, $next->daysInMonth));
    }
}
