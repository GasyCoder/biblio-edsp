<?php

namespace App\Services;

use App\Enums\NumberType;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitService
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    public function checkIn(Student $student, User $operator): Visit
    {
        return DB::transaction(function () use ($student, $operator): Visit {
            $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->id);

            if ($lockedStudent->status !== StudentStatus::Active) {
                throw ValidationException::withMessages(['student' => 'Cet étudiant ne possède pas un statut actif.']);
            }

            if ($lockedStudent->visits()->whereNull('checked_out_at')->exists()) {
                throw ValidationException::withMessages(['student' => 'Une présence est déjà ouverte pour cet étudiant.']);
            }

            return $lockedStudent->visits()->create([
                'visit_number' => $this->numbers->next(NumberType::Visit),
                'checked_in_at' => now(),
                'checked_in_by' => $operator->id,
            ]);
        }, 3);
    }

    public function checkOut(Visit $visit, User $operator): Visit
    {
        return DB::transaction(function () use ($visit, $operator): Visit {
            $lockedVisit = Visit::query()->lockForUpdate()->findOrFail($visit->id);

            if ($lockedVisit->checked_out_at) {
                throw ValidationException::withMessages(['visit' => 'Cette présence est déjà clôturée.']);
            }

            $session = $lockedVisit->consultationSessions()->whereNull('closed_at')->lockForUpdate()->first();
            if ($session && ! $session->closed_at) {
                throw ValidationException::withMessages(['visit' => 'Clôturez la consultation avant d’enregistrer la sortie.']);
            }

            $lockedVisit->update(['checked_out_at' => now(), 'checked_out_by' => $operator->id]);

            return $lockedVisit->refresh();
        }, 3);
    }
}
