<?php

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(): Response
    {
        return Inertia::render('Students/Create', [
            'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $status) => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function store(Request $request, StudentService $students): RedirectResponse
    {
        $student = $students->create($this->validatedData($request));

        return to_route('students.index')->with('success', "Étudiant {$student->registration_number} créé avec succès.");
    }

    public function edit(Student $student): Response
    {
        return Inertia::render('Students/Edit', ['student' => $student, 'statuses' => collect(StudentStatus::cases())->map(fn (StudentStatus $status) => ['value' => $status->value, 'label' => $status->label()])]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $student->update($this->validatedData($request, $student));

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
            'level' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', 'string', 'max:150'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'restriction_reason' => ['nullable', 'string', 'max:2000', 'required_if:status,suspended'],
        ]);
    }
}
