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

    public function activeConsultationItems(): HasMany
    {
        return $this->hasMany(ConsultationItem::class)->whereNull('returned_at');
    }

    public function activeLoanItems(): HasMany
    {
        return $this->hasMany(LoanItem::class)->whereNull('returned_at');
    }

    public function unavailabilityReason(): string
    {
        if ($this->status === CopyStatus::InConsultation) {
            $holder = $this->activeConsultationItems()
                ->with('session.student:id,first_name,last_name')
                ->latest('scanned_at')
                ->first()?->session?->student;

            return $holder
                ? "Cet exemplaire est déjà en consultation par {$holder->first_name} {$holder->last_name}."
                : 'Cet exemplaire est déjà en consultation.';
        }

        if ($this->status === CopyStatus::Borrowed) {
            $loan = $this->activeLoanItems()
                ->with('loan.student:id,first_name,last_name')
                ->latest('loaned_at')
                ->first()?->loan;

            return $loan
                ? "Cet exemplaire est emprunté par {$loan->student->first_name} {$loan->student->last_name} (retour prévu le {$loan->due_at?->format('d/m/Y')})."
                : 'Cet exemplaire est actuellement emprunté.';
        }

        return "Cet exemplaire est indisponible ({$this->status->label()}).";
    }
}
