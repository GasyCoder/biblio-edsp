<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['loan_number', 'student_id', 'opened_at', 'due_at', 'closed_at', 'opened_by', 'closed_by'])]
class Loan extends Model
{
    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'due_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function items(): HasMany { return $this->hasMany(LoanItem::class); }
}
