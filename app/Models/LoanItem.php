<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['loan_id', 'copy_id', 'loaned_at', 'returned_at', 'loaned_by', 'returned_by'])]
class LoanItem extends Model
{
    protected function casts(): array { return ['loaned_at' => 'datetime', 'returned_at' => 'datetime']; }
    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
    public function copy(): BelongsTo { return $this->belongsTo(Copy::class); }
}
