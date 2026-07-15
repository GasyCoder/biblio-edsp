<?php

namespace App\Enums;

enum CardStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active', self::Suspended => 'Suspendue', self::Expired => 'Expirée', self::Replaced => 'Remplacée',
        };
    }
}
