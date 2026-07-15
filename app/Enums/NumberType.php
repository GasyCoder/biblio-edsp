<?php

namespace App\Enums;

enum NumberType: string
{
    case Student = 'student';
    case Copy = 'copy';
    case Visit = 'visit';
    case Consultation = 'consultation';
    case LibraryCard = 'library_card';
    case Loan = 'loan';

    public function prefix(): string
    {
        return match ($this) {
            self::Student, self::LibraryCard => 'BIB',
            self::Copy => 'EDSP-LIV',
            self::Visit => 'EDSP-PTG',
            self::Consultation => 'EDSP-CST',
            self::Loan => 'EDSP-PRT',
        };
    }
}
