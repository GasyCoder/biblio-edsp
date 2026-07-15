<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Models\ConsultationItem;
use App\Models\ConsultationSession;
use App\Models\Copy;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\Visit;
use App\Services\ConsultationService;
use App\Services\VisitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DeskController extends Controller
{
    public function index(Request $request): Response
    {
        $query = trim((string) $request->query('q'));
        $student = $query === '' ? null : $this->findStudent($query);
        $matches = $query !== '' && ! $student ? $this->findCandidates($query) : collect();

        $student?->load(['cards' => fn ($builder) => $builder->where('status', CardStatus::Active), 'visits' => fn ($builder) => $builder->whereNull('checked_out_at')->with(['consultationSession.items.copy.book'])]);

        return Inertia::render('Desk/Index', [
            'query' => $query,
            'student' => $student,
            'visit' => $student?->visits->first(),
            'matches' => $matches,
        ]);
    }

    public function checkIn(Request $request, Student $student, VisitService $service): RedirectResponse
    {
        $service->checkIn($student, $request->user());

        return back()->with('success', 'Entrée enregistrée avec succès.');
    }

    public function checkOut(Request $request, Visit $visit, VisitService $service): RedirectResponse
    {
        $service->checkOut($visit, $request->user());

        return redirect()->route('desk.index')->with('success', 'Sortie enregistrée avec succès.');
    }

    public function openConsultation(Request $request, Visit $visit, ConsultationService $service): RedirectResponse
    {
        $service->open($visit, $request->user());

        return back()->with('success', 'Session de consultation ouverte.');
    }

    public function addCopy(Request $request, ConsultationSession $session, ConsultationService $service): RedirectResponse
    {
        $validated = $request->validate(['barcode' => ['required', 'string', 'max:100']]);
        $copy = Copy::query()->where('barcode_value', trim($validated['barcode']))->orWhere('inventory_number', trim($validated['barcode']))->first();
        if (! $copy) {
            return back()->withErrors(['barcode' => 'Aucun exemplaire ne correspond à ce code.']);
        }

        $service->addCopy($session, $copy, $request->user());

        return back()->with('success', 'Exemplaire ajouté à la consultation.');
    }

    public function returnCopy(Request $request, ConsultationItem $item, ConsultationService $service): RedirectResponse
    {
        $service->returnCopy($item, $request->user());

        return back()->with('success', 'Restitution enregistrée.');
    }

    public function closeConsultation(Request $request, ConsultationSession $session, ConsultationService $service): RedirectResponse
    {
        $service->close($session, $request->user());

        return back()->with('success', 'Session de consultation clôturée.');
    }

    private function findStudent(string $query): ?Student
    {
        $card = StudentCard::query()->where('card_number', $query)->where('status', CardStatus::Active)->first();
        if ($card) {
            return $card->student;
        }

        return Student::query()
            ->where('registration_number', $query)
            ->orWhere('academic_number', $query)
            ->first();
    }

    /** @return Collection<int, Student> */
    private function findCandidates(string $query): Collection
    {
        return Student::query()
            ->where(function ($builder) use ($query) {
                $builder->where('last_name', 'like', "%{$query}%")
                    ->orWhere('first_name', 'like', "%{$query}%");
            })
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'registration_number', 'academic_number', 'first_name', 'last_name', 'status', 'level', 'program']);
    }
}
