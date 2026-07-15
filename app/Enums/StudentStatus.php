<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Graduated = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif', self::Inactive => 'Inactif', self::Suspended => 'Suspendu', self::Graduated => 'Diplômé',
        };
    }
}
