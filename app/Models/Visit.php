<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['visit_number', 'student_id', 'checked_in_at', 'checked_out_at', 'checked_in_by', 'checked_in_role', 'checked_out_by', 'checked_out_role', 'notes'])]
class Visit extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function consultationSession(): HasOne
    {
        return $this->hasOne(ConsultationSession::class)->latestOfMany('opened_at');
    }

    public function consultationSessions(): HasMany
    {
        return $this->hasMany(ConsultationSession::class);
    }
}
