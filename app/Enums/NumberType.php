<?php

namespace App\Enums;

enum NumberType: string
{
    case Student = 'student';
    case Copy = 'copy';
    case Visit = 'visit';
    case Consultation = 'consultation';
    case LibraryCard = 'library_card';

    public function prefix(): string
    {
        return match ($this) {
            self::Student => 'ETU',
            self::Copy => 'EDSP-LIV',
            self::Visit => 'EDSP-PTG',
            self::Consultation => 'EDSP-CST',
            self::LibraryCard => 'BIB',
        };
    }
}
