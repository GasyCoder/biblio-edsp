<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['display_name', 'last_name', 'first_name'])]
class Author extends Model
{
    use HasFactory, SoftDeletes;

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class)->withPivot('position')->withTimestamps();
    }
}
