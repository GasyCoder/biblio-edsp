<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['mention_id', 'code', 'name', 'description', 'is_active'])]
class AcademicProgram extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function mention(): BelongsTo
    {
        return $this->belongsTo(AcademicMention::class, 'mention_id');
    }

    public function levels(): BelongsToMany
    {
        return $this->belongsToMany(AcademicLevel::class, 'academic_level_program', 'program_id', 'level_id');
    }
}
