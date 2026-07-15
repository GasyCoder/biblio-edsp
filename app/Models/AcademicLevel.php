<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'sort_order', 'is_active'])]
class AcademicLevel extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(AcademicProgram::class, 'academic_level_program', 'level_id', 'program_id');
    }
}
