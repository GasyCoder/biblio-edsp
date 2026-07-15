<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_id', 'row_number', 'original_data', 'normalized_data', 'errors', 'status', 'student_id'])]
class ImportRow extends Model
{
    protected function casts(): array
    {
        return ['original_data' => 'array', 'normalized_data' => 'array', 'errors' => 'array'];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
