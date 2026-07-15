<?php

namespace App\Enums;

enum NumberType: string
{
    case Student = 'student';
    case Copy = 'copy';

    public function prefix(): string
    {
        return $this === self::Student ? 'EDSP-ETU' : 'EDSP-LIV';
    }
}
