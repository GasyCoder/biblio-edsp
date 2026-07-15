<?php

namespace App\Models;

use App\Enums\BarcodeSymbology;
use App\Enums\CopyCondition;
use App\Enums\CopyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['book_id', 'location_id', 'inventory_number', 'barcode_value', 'barcode_symbology', 'condition', 'status', 'registered_at', 'notes', 'lock_version'])]
class Copy extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['barcode_symbology' => BarcodeSymbology::class, 'condition' => CopyCondition::class, 'status' => CopyStatus::class, 'registered_at' => 'datetime', 'lock_version' => 'integer'];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function consultationItems(): HasMany
    {
        return $this->hasMany(ConsultationItem::class);
    }
}
