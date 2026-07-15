<?php

namespace App\Enums;

enum CopyStatus: string
{
    case Available = 'available';
    case InConsultation = 'in_consultation';
    case Borrowed = 'borrowed';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible', self::InConsultation => 'En consultation', self::Borrowed => 'Emprunté', self::Damaged => 'Abîmé', self::Lost => 'Perdu', self::Archived => 'Archivé',
        };
    }
}
