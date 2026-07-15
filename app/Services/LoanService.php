<?php

namespace App\Services;

use App\Enums\CopyStatus;
use App\Enums\NumberType;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    public function open(Student $student, string $dueAt, User $operator): Loan
    {
        return DB::transaction(function () use ($student, $dueAt, $operator): Loan {
            $locked = Student::query()->lockForUpdate()->findOrFail($student->id);
            if ($locked->status->value !== 'active') {
                throw ValidationException::withMessages(['loan' => 'Cet étudiant ne possède pas un statut actif.']);
            }
            if ($locked->loans()->whereNull('closed_at')->exists()) {
                throw ValidationException::withMessages(['loan' => 'Un prêt est déjà ouvert pour cet étudiant.']);
            }

            return $locked->loans()->create([
                'loan_number' => $this->numbers->next(NumberType::Loan),
                'opened_at' => now(),
                'due_at' => $dueAt,
                'opened_by' => $operator->id,
            ]);
        }, 3);
    }

    public function addCopy(Loan $loan, Copy $copy, User $operator): LoanItem
    {
        return DB::transaction(function () use ($loan, $copy, $operator): LoanItem {
            $lockedLoan = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            $lockedCopy = Copy::query()->lockForUpdate()->findOrFail($copy->id);
            if ($lockedLoan->closed_at) throw ValidationException::withMessages(['loan_copy' => 'Ce prêt est clôturé.']);
            if ($lockedCopy->status !== CopyStatus::Available) throw ValidationException::withMessages(['loan_copy' => 'Cet exemplaire n’est pas disponible.']);
            if ($lockedLoan->items()->where('copy_id', $lockedCopy->id)->exists()) throw ValidationException::withMessages(['loan_copy' => 'Cet exemplaire figure déjà dans ce prêt.']);

            $item = $lockedLoan->items()->create(['copy_id' => $lockedCopy->id, 'loaned_at' => now(), 'loaned_by' => $operator->id]);
            $lockedCopy->update(['status' => CopyStatus::Borrowed, 'lock_version' => $lockedCopy->lock_version + 1]);

            return $item;
        }, 3);
    }

    public function returnCopy(LoanItem $item, User $operator): LoanItem
    {
        return DB::transaction(function () use ($item, $operator): LoanItem {
            $lockedItem = LoanItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($lockedItem->returned_at) throw ValidationException::withMessages(['loan_copy' => 'Cet exemplaire a déjà été rendu.']);
            $copy = Copy::query()->lockForUpdate()->findOrFail($lockedItem->copy_id);
            $lockedItem->update(['returned_at' => now(), 'returned_by' => $operator->id]);
            $copy->update(['status' => CopyStatus::Available, 'lock_version' => $copy->lock_version + 1]);

            return $lockedItem->refresh();
        }, 3);
    }

    public function close(Loan $loan, User $operator): Loan
    {
        return DB::transaction(function () use ($loan, $operator): Loan {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($locked->closed_at) throw ValidationException::withMessages(['loan' => 'Ce prêt est déjà clôturé.']);
            if ($locked->items()->whereNull('returned_at')->exists()) throw ValidationException::withMessages(['loan' => 'Tous les exemplaires doivent être rendus avant la clôture.']);
            $locked->update(['closed_at' => now(), 'closed_by' => $operator->id]);

            return $locked->refresh();
        }, 3);
    }
}
