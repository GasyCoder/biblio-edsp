<?php

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\AcademicReferenceService;
use App\Services\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function update(Request $request, Student $student, AcademicReferenceService $academicReferences): RedirectResponse
    {
        $student->update($academicReferences->resolve($this->validatedData($request, $student)));

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
