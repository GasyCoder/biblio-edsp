<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'scope', 'current_value'])]
class NumberSequence extends Model
{
    protected function casts(): array
    {
        return ['current_value' => 'integer'];
    }
}
