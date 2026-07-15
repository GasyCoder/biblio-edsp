<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['consultation_session_id', 'copy_id', 'scanned_at', 'returned_at', 'scanned_by', 'returned_by'])]
class ConsultationItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime', 'returned_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ConsultationSession::class, 'consultation_session_id');
    }

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Copy::class);
    }
}
