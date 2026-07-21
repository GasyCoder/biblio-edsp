<?php

namespace App\Http\Controllers;

use App\Enums\BarcodeSymbology;
use App\Enums\CardStatus;
use App\Enums\CardType;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\Setting;
use App\Services\BarcodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentCardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cards/Index', ['cards' => StudentCard::query()->with('student:id,registration_number,last_name,first_name,academic_number,photo_path')->latest()->paginate(20)]);
    }

    public function create(): Response
    {
        return Inertia::render('Cards/Create', [
            'students' => Student::query()
                ->where('status', 'active')
                ->whereDoesntHave('cards', fn ($query) => $query->where('status', 'active'))
                ->with(['academicLevel:id,name', 'academicProgram:id,name'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'registration_number', 'academic_number', 'last_name', 'first_name', 'photo_path', 'level_id', 'program_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->filled('student_ids')) {
            $data = $request->validate([
                'student_ids' => ['required', 'array', 'min:1', 'max:100'],
                'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
                'expires_at' => ['nullable', 'date', 'after:today'],
            ]);
            $studentIds = $data['student_ids'];
            $studentErrorKey = 'student_ids';
        } else {
            $data = $request->validate(['student_id' => ['required', 'exists:students,id'], 'expires_at' => ['nullable', 'date', 'after:today']]);
            $studentIds = [$data['student_id']];
            $studentErrorKey = 'student_id';
        }

        $cards = DB::transaction(function () use ($studentIds, $studentErrorKey, $data, $request) {
            $students = Student::query()->whereKey($studentIds)->orderBy('id')->lockForUpdate()->get();

            return $students->map(function (Student $student) use ($studentErrorKey, $data, $request): StudentCard {
                if ($student->cards()->where('status', CardStatus::Active)->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([$studentErrorKey => "{$student->last_name} {$student->first_name} possède déjà une carte active."]);
                }

                $card = $student->cards()->latest('id')->lockForUpdate()->first();
                $attributes = [
                    'student_id' => $student->id,
                    'expires_at' => $data['expires_at'] ?? now()->addMonths((int) Setting::getValue('card_validity_months', 12)),
                    'card_number' => $student->registration_number,
                    'type' => CardType::Library,
                    'symbology' => BarcodeSymbology::Qr,
                    'status' => CardStatus::Active,
                    'issued_at' => now(),
                    'created_by' => $request->user()->id,
                ];
                if ($card) {
                    $card->update($attributes);

                    return $card->refresh();
                }

                return StudentCard::query()->create($attributes);
            });
        }, 3);

        return to_route('cards.index')->with('success', $cards->count().' carte(s) de bibliothèque créée(s).');
    }

    public function edit(StudentCard $card): Response
    {
        return Inertia::render('Cards/Edit', [
            'card' => $card->load('student:id,registration_number,last_name,first_name,photo_path'),
            'statuses' => collect([CardStatus::Active, CardStatus::Suspended, CardStatus::Expired])->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()]),
        ]);
    }

    public function update(Request $request, StudentCard $card): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in([CardStatus::Active->value, CardStatus::Suspended->value, CardStatus::Expired->value])], 'expires_at' => ['nullable', 'date']]);
        if ($data['status'] === CardStatus::Active->value && ! empty($data['expires_at']) && now()->startOfDay()->gt($data['expires_at'])) {
            throw ValidationException::withMessages(['expires_at' => 'Une carte active ne peut pas avoir une date d’expiration passée.']);
        }

        DB::transaction(function () use ($card, $data) {
            $locked = StudentCard::query()->lockForUpdate()->findOrFail($card->id);
            if ($data['status'] === CardStatus::Active->value && StudentCard::query()->where('student_id', $locked->student_id)->where('status', CardStatus::Active)->whereKeyNot($locked->id)->exists()) {
                throw ValidationException::withMessages(['status' => 'Cet étudiant possède déjà une autre carte active.']);
            }
            $locked->update($data);
        }, 3);

        return to_route('cards.index')->with('success', "Carte de bibliothèque {$card->card_number} mise à jour.");
    }

    public function destroy(StudentCard $card): RedirectResponse
    {
        $number = $card->card_number;
        $card->delete();

        return to_route('cards.index')->with('success', "Carte de bibliothèque {$number} supprimée.");
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:student_cards,id']]);
        $cards = StudentCard::query()->whereKey($data['ids'])->get();
        DB::transaction(fn () => $cards->each->delete());

        return to_route('cards.index')->with('success', $cards->count().' carte(s) de bibliothèque supprimée(s).');
    }

    public function print(StudentCard $card, BarcodeService $barcodes): View
    {
        $card->load('student.academicLevel', 'student.academicProgram');

        return view('print.student-card', ['card' => $card, 'codeSvg' => $card->symbology === BarcodeSymbology::Qr ? $barcodes->qrSvg($card->card_number, 220) : $barcodes->code128Svg($card->card_number), 'embedded' => request()->boolean('embedded'), 'branding' => $this->cardBranding()]);
    }

    public function printBulk(Request $request, BarcodeService $barcodes): View
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:2', 'max:50'], 'ids.*' => ['integer', 'distinct', 'exists:student_cards,id']]);
        $order = array_flip($data['ids']);
        $cards = StudentCard::query()->whereKey($data['ids'])->with(['student.academicLevel', 'student.academicProgram'])->get()->sortBy(fn (StudentCard $card) => $order[$card->id])->values();

        return view('print.student-cards', ['cards' => $cards, 'codes' => $cards->mapWithKeys(fn (StudentCard $card) => [$card->id => $barcodes->qrSvg($card->card_number, 220)]), 'branding' => $this->cardBranding()]);
    }

    /** @return array{name: string, school: string, institution: string, logo_url: ?string} */
    private function cardBranding(): array
    {
        $logoPath = Setting::getValue('logo_path');

        return [
            'name' => Setting::getValue('library_name', 'Bibliothèque EDSP'),
            'school' => Setting::getValue('school_name', 'École de Droit et Sciences Politiques'),
            'institution' => Setting::getValue('institution_name', 'Université de Mahajanga'),
            'logo_url' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
        ];
    }
}
