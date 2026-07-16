<?php

namespace App\Services;

use App\Enums\CopyStatus;
use App\Enums\NumberType;
use App\Models\ConsultationItem;
use App\Models\ConsultationSession;
use App\Models\Copy;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationService
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    public function open(Visit $visit, User $operator): ConsultationSession
    {
        return DB::transaction(function () use ($visit, $operator): ConsultationSession {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            if ($lockedVisit->checked_out_at) {
                throw ValidationException::withMessages(['visit' => 'La présence est déjà clôturée.']);
            }

            if ($lockedVisit->consultationSessions()->whereNull('closed_at')->exists()) {
                throw ValidationException::withMessages(['visit' => 'Une consultation est déjà ouverte pour cette présence.']);
            }

            return ConsultationSession::create([
                'session_number' => $this->numbers->next(NumberType::Consultation),
                'student_id' => $lockedVisit->student_id,
                'visit_id' => $lockedVisit->id,
                'opened_at' => now(),
                'opened_by' => $operator->id,
            ]);
        }, 3);
    }

    public function addCopy(ConsultationSession $session, Copy $copy, User $operator): ConsultationItem
    {
        return DB::transaction(function () use ($session, $copy, $operator): ConsultationItem {
            $lockedSession = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);
            $lockedCopy = Copy::query()->lockForUpdate()->findOrFail($copy->id);

            if ($lockedSession->closed_at) {
                throw ValidationException::withMessages(['copy' => 'Cette consultation est clôturée.']);
            }
            if ($lockedCopy->status !== CopyStatus::Available) {
                throw ValidationException::withMessages(['copy' => 'Cet exemplaire n’est pas disponible.']);
            }
            if ($lockedSession->items()->where('copy_id', $lockedCopy->id)->exists()) {
                throw ValidationException::withMessages(['copy' => 'Cet exemplaire a déjà été scanné dans cette session.']);
            }

            $item = $lockedSession->items()->create([
                'copy_id' => $lockedCopy->id,
                'scanned_at' => now(),
                'scanned_by' => $operator->id,
            ]);
            $lockedCopy->update(['status' => CopyStatus::InConsultation, 'lock_version' => $lockedCopy->lock_version + 1]);

            return $item;
        }, 3);
    }

    public function returnCopy(ConsultationItem $item, User $operator): ConsultationItem
    {
        return DB::transaction(function () use ($item, $operator): ConsultationItem {
            $lockedItem = ConsultationItem::query()->lockForUpdate()->findOrFail($item->id);
            $lockedCopy = Copy::query()->lockForUpdate()->findOrFail($lockedItem->copy_id);

            if ($lockedItem->returned_at) {
                throw ValidationException::withMessages(['copy' => 'Cet exemplaire a déjà été restitué.']);
            }

            $lockedItem->update(['returned_at' => now(), 'returned_by' => $operator->id]);
            $lockedCopy->update(['status' => CopyStatus::Available, 'lock_version' => $lockedCopy->lock_version + 1]);

            return $lockedItem->refresh();
        }, 3);
    }

    public function close(ConsultationSession $session, User $operator): ConsultationSession
    {
        return DB::transaction(function () use ($session, $operator): ConsultationSession {
            $lockedSession = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);
            if ($lockedSession->closed_at) {
                throw ValidationException::withMessages(['session' => 'Cette consultation est déjà clôturée.']);
            }

            $activeItems = $lockedSession->items()->whereNull('returned_at')->lockForUpdate()->get();
            foreach ($activeItems as $item) {
                $copy = Copy::query()->lockForUpdate()->findOrFail($item->copy_id);
                $item->update(['returned_at' => now(), 'returned_by' => $operator->id]);
                $copy->update([
                    'status' => CopyStatus::Available,
                    'lock_version' => $copy->lock_version + 1,
                ]);
            }

            $lockedSession->update(['closed_at' => now(), 'closed_by' => $operator->id]);

            return $lockedSession->refresh();
        }, 3);
    }
}
