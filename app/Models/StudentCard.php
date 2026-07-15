<?php

namespace App\Models;

use App\Enums\BarcodeSymbology;
use App\Enums\CardStatus;
use App\Enums\CardType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'card_number', 'type', 'symbology', 'status', 'issued_at', 'expires_at', 'replaced_by_id', 'created_by'])]
class StudentCard extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['type' => CardType::class, 'symbology' => BarcodeSymbology::class, 'status' => CardStatus::class, 'issued_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }
}
