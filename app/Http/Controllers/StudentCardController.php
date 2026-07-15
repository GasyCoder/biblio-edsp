<?php

namespace App\Http\Controllers;

use App\Enums\BarcodeSymbology;
use App\Enums\CardStatus;
use App\Enums\CardType;
use App\Models\Student;
use App\Models\StudentCard;
use App\Services\BarcodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudentCardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Cards/Index', ['cards' => StudentCard::query()->with('student:id,registration_number,last_name,first_name,academic_number')->latest()->paginate(20)]);
    }

    public function create(): Response
    {
        return Inertia::render('Cards/Create', ['students' => Student::query()->where('status', 'active')->whereDoesntHave('cards', fn ($query) => $query->where('status', 'active'))->orderBy('last_name')->get(['id', 'registration_number', 'academic_number', 'last_name', 'first_name'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['student_id' => ['required', 'exists:students,id'], 'type' => ['required', Rule::enum(CardType::class)], 'symbology' => ['required', Rule::enum(BarcodeSymbology::class)], 'expires_at' => ['nullable', 'date', 'after:today']]);
        $card = DB::transaction(function () use ($data, $request) {
            $student = Student::query()->lockForUpdate()->findOrFail($data['student_id']);
            if ($student->cards()->where('status', CardStatus::Active)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['student_id' => 'Cet étudiant possède déjà une carte active.']);
            }

            return StudentCard::query()->create([...$data, 'card_number' => 'EDSP:CARD:1:'.Str::ulid(), 'status' => CardStatus::Active, 'issued_at' => now(), 'created_by' => $request->user()->id]);
        }, 3);

        return to_route('cards.index')->with('success', "Carte {$card->card_number} créée.");
    }

    public function print(StudentCard $card, BarcodeService $barcodes): View
    {
        $card->load('student');

        return view('print.student-card', ['card' => $card, 'codeSvg' => $card->symbology === BarcodeSymbology::Qr ? $barcodes->qrSvg($card->card_number, 220) : $barcodes->code128Svg($card->card_number)]);
    }
}
