<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\Visit;
use App\Services\VisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceStationController extends Controller
{
    public function show(Request $request, string $mode): Response
    {
        $this->validateMode($mode);

        return Inertia::render('Attendance/Station', [
            'mode' => $mode,
            'recentScans' => Visit::query()
                ->with('student:id,registration_number,first_name,last_name,photo_path')
                ->when($mode === 'entry', fn ($query) => $query->whereNotNull('checked_in_at')->latest('checked_in_at'))
                ->when($mode === 'exit', fn ($query) => $query->whereNotNull('checked_out_at')->latest('checked_out_at'))
                ->limit(6)
                ->get()
                ->map(fn (Visit $visit) => [
                    'id' => $visit->id,
                    'student' => $visit->student,
                    'scanned_at' => ($mode === 'entry' ? $visit->checked_in_at : $visit->checked_out_at)?->toIso8601String(),
                ]),
        ]);
    }

    public function scan(Request $request, string $mode, VisitService $visits): RedirectResponse
    {
        $this->validateMode($mode);
        $data = $request->validate(['code' => ['required', 'string', 'max:150']]);
        $student = $this->resolveStudent($data['code']);

        if (! $student) {
            throw ValidationException::withMessages(['code' => 'Carte inconnue ou inactive. Vérifiez le QR code présenté.']);
        }

        $openVisit = $student->visits()->whereNull('checked_out_at')->first();

        if ($mode === 'entry') {
            if ($openVisit) {
                return back()->with('info', "{$student->first_name} {$student->last_name} est déjà présent(e). Aucun doublon créé.");
            }

            $visits->checkIn($student, $request->user());

            return back()->with('success', "Entrée enregistrée pour {$student->first_name} {$student->last_name}.");
        }

        if (! $openVisit) {
            return back()->with('info', "Aucune présence ouverte pour {$student->first_name} {$student->last_name}. Aucun pointage modifié.");
        }

        $visits->checkOut($openVisit, $request->user());

        return back()->with('success', "Sortie enregistrée pour {$student->first_name} {$student->last_name}. Les prêts à domicile restent ouverts.");
    }

    private function resolveStudent(string $code): ?Student
    {
        $value = strtoupper(trim($code));
        $normalized = preg_match('/^(?:BIB-)?(\d{2})-(\d+)$/', $value, $matches) === 1
            ? sprintf('BIB-%s-%03d', $matches[1], (int) $matches[2])
            : $value;

        $card = StudentCard::query()
            ->where('status', CardStatus::Active)
            ->whereIn('card_number', array_unique([$value, $normalized]))
            ->first();

        return $card?->student ?? Student::query()->whereIn('registration_number', array_unique([$value, $normalized]))->first();
    }

    private function validateMode(string $mode): void
    {
        validator(['mode' => $mode], ['mode' => ['required', Rule::in(['entry', 'exit'])]])->validate();
    }
}
