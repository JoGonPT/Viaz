<?php

declare(strict_types=1);

namespace App;

class TripPresenter
{
    public static function label(string $status): string
    {
        return match ($status) {
            'open' => 'Pendente',
            'assigned' => 'Aceite',
            'in_progress' => 'A decorrer',
            'completed' => 'Concluída',
            'cancelled' => 'Cancelada',
            default => $status,
        };
    }

    public static function icon(string $status): string
    {
        return match ($status) {
            'open' => '🕒',
            'assigned' => '✅',
            'in_progress' => '🚗',
            'completed' => '🏁',
            'cancelled' => '❌',
            default => '•',
        };
    }

    public static function badgeClasses(string $status): string
    {
        return match ($status) {
            'open' => 'bg-amber-100 text-amber-700',
            'assigned' => 'bg-emerald-100 text-emerald-700',
            'in_progress' => 'bg-sky-100 text-sky-700',
            'completed' => 'bg-slate-200 text-slate-600',
            'cancelled' => 'bg-red-100 text-red-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }

    public static function iconBgClasses(string $status): string
    {
        return match ($status) {
            'open' => 'bg-amber-100',
            'assigned' => 'bg-emerald-100',
            'in_progress' => 'bg-sky-100',
            'completed' => 'bg-slate-200',
            'cancelled' => 'bg-red-100',
            default => 'bg-slate-100',
        };
    }
}
