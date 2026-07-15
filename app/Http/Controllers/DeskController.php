<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Models\ConsultationItem;
use App\Models\ConsultationSession;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\Visit;
use App\Services\ConsultationService;
use App\Services\LoanService;
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

        $student?->load([
            'academicLevel:id,code,name',
            'mention:id,code,name',
            'academicProgram:id,code,name',
            'cards' => fn ($builder) => $builder->where('status', CardStatus::Active)->latest('issued_at'),
            'visits' => fn ($builder) => $builder->whereNull('checked_out_at')->with(['consultationSession.items.copy.book']),
            'loans' => fn ($builder) => $builder->whereNull('closed_at')->with(['items.copy.book']),
        ]);

        return Inertia::render('Desk/Index', [
            'query' => $query,
            'student' => $student,
            'visit' => $student?->visits->first(),
            'loan' => $student?->loans->first(),
            'matches' => $matches,
            'recentActivity' => $this->recentActivity(),
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

    public function openLoan(Request $request, Student $student, LoanService $service): RedirectResponse
    {
        $data = $request->validate(['due_at' => ['required', 'date', 'after:today']]);
        $service->open($student, $data['due_at'], $request->user());

        return back()->with('success', 'Prêt ouvert. Scannez maintenant les exemplaires.');
    }

    public function addLoanCopy(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        $data = $request->validate(['barcode' => ['required', 'string', 'max:100']]);
        $value = trim($data['barcode']);
        $copy = Copy::query()->where('barcode_value', $value)->orWhere('inventory_number', $value)->first();
        if (! $copy) return back()->withErrors(['loan_copy' => 'Aucun exemplaire ne correspond à ce code.']);
        $service->addCopy($loan, $copy, $request->user());

        return back()->with('success', 'Exemplaire ajouté au prêt.');
    }

    public function returnLoanCopy(Request $request, LoanItem $item, LoanService $service): RedirectResponse
    {
        $service->returnCopy($item, $request->user());

        return back()->with('success', 'Retour du prêt enregistré.');
    }

    public function closeLoan(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        $service->close($loan, $request->user());

        return back()->with('success', 'Prêt clôturé.');
    }

    private function findStudent(string $query): ?Student
    {
        $normalized = $this->normalizeLibraryNumber($query);
        $card = StudentCard::query()->whereIn('card_number', array_unique([$query, $normalized]))->where('status', CardStatus::Active)->first();
        if ($card) {
            return $card->student;
        }

        return Student::query()
            ->whereIn('registration_number', array_unique([$query, $normalized]))
            ->orWhere('academic_number', $query)
            ->first();
    }

    private function normalizeLibraryNumber(string $query): string
    {
        $value = strtoupper(trim($query));
        if (preg_match('/^(?:BIB-)?(\d{2})-(\d+)$/', $value, $matches) === 1) {
            return sprintf('BIB-%s-%03d', $matches[1], (int) $matches[2]);
        }

        return $query;
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

    /** @return Collection<int, array<string, mixed>> */
    private function recentActivity(): Collection
    {
        $visits = Visit::query()->with('student:id,registration_number,last_name,first_name')->latest('checked_in_at')->limit(12)->get()
            ->flatMap(function (Visit $visit): array {
                $events = [[
                    'key' => "visit-in-{$visit->id}", 'type' => 'entry', 'label' => 'Entrée enregistrée',
                    'occurred_at' => $visit->checked_in_at?->toIso8601String(), 'student' => $visit->student,
                    'book' => null, 'inventory_number' => null,
                ]];
                if ($visit->checked_out_at) $events[] = [
                    'key' => "visit-out-{$visit->id}", 'type' => 'exit', 'label' => 'Sortie enregistrée',
                    'occurred_at' => $visit->checked_out_at->toIso8601String(), 'student' => $visit->student,
                    'book' => null, 'inventory_number' => null,
                ];

                return $events;
            });

        $consultations = ConsultationItem::query()->with(['session.student:id,registration_number,last_name,first_name', 'copy.book:id,title'])->latest('scanned_at')->limit(12)->get()
            ->flatMap(function (ConsultationItem $item): array {
                $base = ['student' => $item->session->student, 'book' => $item->copy->book?->title, 'inventory_number' => $item->copy->inventory_number];
                $events = [[...$base, 'key' => "consultation-scan-{$item->id}", 'type' => 'consultation', 'label' => 'Livre scanné pour lecture', 'occurred_at' => $item->scanned_at?->toIso8601String()]];
                if ($item->returned_at) $events[] = [...$base, 'key' => "consultation-return-{$item->id}", 'type' => 'return', 'label' => 'Livre restitué', 'occurred_at' => $item->returned_at->toIso8601String()];

                return $events;
            });

        $loans = LoanItem::query()->with(['loan.student:id,registration_number,last_name,first_name', 'copy.book:id,title'])->latest('loaned_at')->limit(12)->get()
            ->flatMap(function (LoanItem $item): array {
                $base = ['student' => $item->loan->student, 'book' => $item->copy->book?->title, 'inventory_number' => $item->copy->inventory_number];
                $events = [[...$base, 'key' => "loan-scan-{$item->id}", 'type' => 'loan', 'label' => 'Livre scanné pour prêt', 'occurred_at' => $item->loaned_at?->toIso8601String()]];
                if ($item->returned_at) $events[] = [...$base, 'key' => "loan-return-{$item->id}", 'type' => 'return', 'label' => 'Retour de prêt', 'occurred_at' => $item->returned_at->toIso8601String()];

                return $events;
            });

        return $visits->merge($consultations)->merge($loans)
            ->filter(fn (array $event) => $event['occurred_at'])
            ->sortByDesc('occurred_at')->take(15)->values();
    }
}
