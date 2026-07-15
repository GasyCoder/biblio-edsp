<?php

namespace App\Enums;

enum CardType: string
{
    case Student = 'student';
    case Library = 'library';

    public function label(): string
    {
        return 'Carte de bibliothèque';
    }
}
