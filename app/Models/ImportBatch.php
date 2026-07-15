<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'original_filename', 'stored_path', 'sheet_name', 'status', 'total_rows', 'valid_rows', 'error_rows', 'imported_rows', 'uploaded_by', 'committed_by', 'committed_at'])]
class ImportBatch extends Model
{
    protected $table = 'imports';

    protected $hidden = ['stored_path'];

    protected function casts(): array
    {
        return ['committed_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class, 'import_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
