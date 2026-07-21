<?php

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Models\ConsultationItem;
use App\Models\ConsultationSession;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Student;
use App\Models\Visit;
use App\Services\AcademicReferenceService;
use App\Services\AttendanceScore;
use App\Services\StudentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $students = Student::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('academic_number', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(AcademicReferenceService $academicReferences): Response
    {
        return Inertia::render('Students/Create', [
            'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
            'academicReferences' => $academicReferences->tree(),
        ]);
    }

    public function store(Request $request, StudentService $students): RedirectResponse
    {
        $student = $students->create($this->validatedData($request));

        return to_route('students.index')->with('success', "Étudiant {$student->registration_number} créé avec succès.");
    }

    public function edit(Student $student, AcademicReferenceService $academicReferences): Response
    {
        return Inertia::render('Students/Edit', ['student' => $student, 'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $status) => ['value' => $status->value, 'label' => $status->label()]), 'academicReferences' => $academicReferences->tree()]);
    }

    public function show(Student $student): Response
    {
        return Inertia::render('Students/Show', [
            'student' => $student->load([
                'academicLevel:id,code,name', 'mention:id,code,name', 'academicProgram:id,code,name',
                'cards' => fn ($query) => $query->latest('issued_at'),
                'visits' => fn ($query) => $query->latest('checked_in_at')->limit(10),
                'consultationSessions' => fn ($query) => $query->withCount('items')->latest('opened_at')->limit(10),
            ]),
            'consultedBooks' => ConsultationItem::query()
                ->whereHas('session', fn ($query) => $query->where('student_id', $student->id))
                ->with(['session:id,session_number,opened_at,closed_at', 'copy.book'])
                ->latest('scanned_at')
                ->paginate(12, ['*'], 'consultations_page')
                ->withQueryString(),
            'borrowedBooks' => LoanItem::query()
                ->whereHas('loan', fn ($query) => $query->where('student_id', $student->id))
                ->with(['loan:id,loan_number,opened_at,due_at,closed_at', 'copy.book'])
                ->latest('loaned_at')
                ->paginate(12, ['*'], 'loans_page')
                ->withQueryString(),
            'attendance' => $this->attendance($student),
        ]);
    }

    /**
     * Assiduité individuelle sur les 30 derniers jours, avec le même modèle
     * que le rapport global : « présent un jour » = au moins un passage ce jour-là.
     *
     * @return array<string, mixed>
     */
    private function attendance(Student $student): array
    {
        $to = now()->endOfDay();
        $from = now()->subDays(29)->startOfDay();

        $daysPresent = (int) Visit::query()->where('student_id', $student->id)
            ->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('count(distinct date(checked_in_at)) as total')->value('total');
        $visits = (int) Visit::query()->where('student_id', $student->id)
            ->whereBetween('checked_in_at', [$from, $to])->count();
        $consultations = (int) ConsultationSession::query()->where('student_id', $student->id)
            ->whereBetween('opened_at', [$from, $to])->count();
        $loans = (int) Loan::query()->where('student_id', $student->id)
            ->whereBetween('opened_at', [$from, $to])->count();
        $openDays = (int) Visit::query()->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('count(distinct date(checked_in_at)) as total')->value('total');

        [$rank, $cohortSize] = $this->rankInCohort($student, $from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'daysPresent' => $daysPresent,
            'visits' => $visits,
            'consultations' => $consultations,
            'loans' => $loans,
            'openDays' => $openDays,
            'rate' => $openDays ? (int) round($daysPresent / $openDays * 100) : 0,
            'score' => AttendanceScore::compute($daysPresent, $consultations, $loans),
            'weights' => AttendanceScore::weights(),
            'rank' => $rank,
            'cohortSize' => $cohortSize,
            'cohortLabel' => $student->academicLevel?->name ?? ($student->level ?: 'tous niveaux'),
            'weeks' => $this->weeklyPresence($student),
        ];
    }

    /**
     * Rang de l'étudiant parmi les étudiants actifs de son niveau, au score combiné.
     *
     * @return array{0: int|null, 1: int}
     */
    private function rankInCohort(Student $student, Carbon $from, Carbon $to): array
    {
        $cohortIds = Student::query()->where('status', StudentStatus::Active)
            ->when($student->level_id, fn ($query) => $query->where('level_id', $student->level_id))
            ->pluck('id');

        if ($cohortIds->count() < 2 || ! $cohortIds->contains($student->id)) {
            return [null, $cohortIds->count()];
        }

        $days = Visit::query()->whereIn('student_id', $cohortIds)->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('student_id, count(distinct date(checked_in_at)) as total')->groupBy('student_id')->pluck('total', 'student_id');
        $consultations = ConsultationSession::query()->whereIn('student_id', $cohortIds)->whereBetween('opened_at', [$from, $to])
            ->selectRaw('student_id, count(*) as total')->groupBy('student_id')->pluck('total', 'student_id');
        $loans = Loan::query()->whereIn('student_id', $cohortIds)->whereBetween('opened_at', [$from, $to])
            ->selectRaw('student_id, count(*) as total')->groupBy('student_id')->pluck('total', 'student_id');

        $scores = $cohortIds->mapWithKeys(fn (int $id) => [$id => AttendanceScore::compute(
            (int) ($days[$id] ?? 0),
            (int) ($consultations[$id] ?? 0),
            (int) ($loans[$id] ?? 0),
        )]);

        $mine = $scores[$student->id] ?? 0;
        $rank = $mine > 0 ? $scores->filter(fn (int $score) => $score > $mine)->count() + 1 : null;

        return [$rank, $cohortIds->count()];
    }

    /**
     * Jours de présence par semaine sur les 8 dernières semaines.
     *
     * @return array<int, array{label: string, days: int}>
     */
    private function weeklyPresence(Student $student): array
    {
        $start = now()->subWeeks(7)->startOfWeek();
        $presenceDays = Visit::query()->where('student_id', $student->id)
            ->where('checked_in_at', '>=', $start)
            ->selectRaw('date(checked_in_at) as day')->distinct()->pluck('day');

        $byWeek = $presenceDays->groupBy(fn (string $day) => Carbon::parse($day)->isoFormat('GGGG-[W]WW'));
        $weeks = [];
        $cursor = $start->copy();
        while ($cursor <= now()) {
            $key = $cursor->isoFormat('GGGG-[W]WW');
            $weeks[] = ['label' => 'S'.$cursor->isoWeek(), 'days' => $byWeek->get($key)?->count() ?? 0];
            $cursor->addWeek();
        }

        return $weeks;
    }

    public function update(Request $request, Student $student, AcademicReferenceService $academicReferences): RedirectResponse
    {
        $data = $this->validatedData($request, $student);
        $oldPhoto = $student->photo_path;
        if (! empty($data['photo'])) {
            $data['photo_path'] = $data['photo']->store('photos/students', 'public');
        } elseif (! empty($data['remove_photo'])) {
            $data['photo_path'] = null;
        }
        $student->update($academicReferences->resolve(collect($data)->except(['photo', 'remove_photo'])->all()));
        if ($oldPhoto && $oldPhoto !== $student->photo_path) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return to_route('students.index')->with('success', "Étudiant {$student->registration_number} mis à jour.");
    }

    public function destroy(Student $student): RedirectResponse
    {
        if ($student->cards()->exists() || $student->visits()->exists() || $student->consultationSessions()->exists()) {
            return back()->withErrors(['student' => 'Cet étudiant possède une carte ou un historique. Passez plutôt son statut à Inactif.']);
        }
        $number = $student->registration_number;
        $student->delete();

        return to_route('students.index')->with('success', "Étudiant {$number} supprimé.");
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:students,id']]);
        $students = Student::query()->whereKey($data['ids'])->withCount(['cards', 'visits', 'consultationSessions'])->get();
        $protected = $students->first(fn (Student $student) => $student->cards_count > 0 || $student->visits_count > 0 || $student->consultation_sessions_count > 0);

        if ($protected) {
            return back()->withErrors(['student' => "L’étudiant {$protected->registration_number} possède une carte ou un historique. La suppression groupée a été annulée ; passez plutôt son statut à Inactif."]);
        }

        DB::transaction(fn () => $students->each->delete());

        return to_route('students.index')->with('success', $students->count().' étudiant(s) supprimé(s).');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'academic_number' => ['nullable', 'string', 'max:64', Rule::unique('students', 'academic_number')->ignore($student)],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', Rule::in(['female', 'male', 'other'])],
            'repetition_code' => ['sometimes', Rule::in(['N', 'R', 'T'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_photo' => ['nullable', 'boolean'],
            'level_id' => ['nullable', 'integer', 'exists:academic_levels,id'],
            'mention_id' => ['nullable', 'integer', 'exists:academic_mentions,id'],
            'program_id' => ['nullable', 'integer', 'exists:academic_programs,id'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'restriction_reason' => ['nullable', 'string', 'max:2000', 'required_if:status,suspended'],
        ]);
    }
}
