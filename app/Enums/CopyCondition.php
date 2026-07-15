<?php

namespace App\Enums;

enum CopyCondition: string
{
    case New = 'new';
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Neuf', self::Good => 'Bon', self::Fair => 'Moyen', self::Poor => 'Mauvais',
        };
    }
}
