<?php

namespace App\Http\Controllers;

use App\Enums\CopyStatus;
use App\Models\ConsultationItem;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\Student;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($validated['from'] ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay();

        $visits = Visit::query()->whereBetween('checked_in_at', [$from, $to]);
        $consultations = ConsultationItem::query()->whereBetween('scanned_at', [$from, $to]);
        $loans = Loan::query()->whereBetween('opened_at', [$from, $to]);

        $consultedByBook = DB::table('consultation_items')->join('copies', 'copies.id', '=', 'consultation_items.copy_id')
            ->whereBetween('consultation_items.scanned_at', [$from, $to])->groupBy('copies.book_id')->selectRaw('copies.book_id, count(*) as total')->pluck('total', 'book_id');
        $loanedByBook = DB::table('loan_items')->join('copies', 'copies.id', '=', 'loan_items.copy_id')
            ->whereBetween('loan_items.loaned_at', [$from, $to])->groupBy('copies.book_id')->selectRaw('copies.book_id, count(*) as total')->pluck('total', 'book_id');
        $bookIds = $consultedByBook->keys()->merge($loanedByBook->keys())->unique();
        $books = DB::table('books')->whereIn('id', $bookIds)->get(['id', 'title'])->map(function ($book) use ($consultedByBook, $loanedByBook) {
            $book->consultations = (int) ($consultedByBook[$book->id] ?? 0); $book->loans = (int) ($loanedByBook[$book->id] ?? 0); $book->total = $book->consultations + $book->loans; return $book;
        })->sortByDesc('total')->take(8)->values();

        $categories = DB::table('consultation_items')->join('copies', 'copies.id', '=', 'consultation_items.copy_id')->join('books', 'books.id', '=', 'copies.book_id')->leftJoin('categories', 'categories.id', '=', 'books.category_id')
            ->whereBetween('consultation_items.scanned_at', [$from, $to])->groupBy('categories.id', 'categories.name')->selectRaw("coalesce(categories.name, 'Sans catégorie') as name, count(*) as total")->orderByDesc('total')->limit(6)->get();

        $students = Student::query()->withCount([
            'visits' => fn (Builder $query) => $query->whereBetween('checked_in_at', [$from, $to]),
            'consultationSessions' => fn (Builder $query) => $query->whereBetween('opened_at', [$from, $to]),
            'loans' => fn (Builder $query) => $query->whereBetween('opened_at', [$from, $to]),
        ])->get(['id', 'registration_number', 'last_name', 'first_name', 'photo_path'])->map(function (Student $student) {
            $student->activity_total = $student->visits_count + $student->consultation_sessions_count + $student->loans_count; return $student;
        })->sortByDesc('activity_total')->take(8)->values();

        $dailyVisits = Visit::query()->whereBetween('checked_in_at', [$from, $to])->selectRaw('date(checked_in_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');
        $dailyConsultations = ConsultationItem::query()->whereBetween('scanned_at', [$from, $to])->selectRaw('date(scanned_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');
        $trend = collect(range(0, $from->diffInDays($to)))->map(function (int $offset) use ($from, $dailyVisits, $dailyConsultations) {
            $day = $from->copy()->addDays($offset)->toDateString(); return ['day' => $day, 'visits' => (int) ($dailyVisits[$day] ?? 0), 'consultations' => (int) ($dailyConsultations[$day] ?? 0)];
        });

        $inventory = Copy::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'metrics' => ['visits' => (clone $visits)->count(), 'uniqueStudents' => (clone $visits)->distinct()->count('student_id'), 'consultations' => $consultations->count(), 'loans' => $loans->count()],
            'alerts' => ['present' => Visit::query()->whereNull('checked_out_at')->count(), 'overdueLoans' => Loan::query()->whereNull('closed_at')->where('due_at', '<', now())->count(), 'unavailableCopies' => Copy::query()->whereIn('status', [CopyStatus::Damaged, CopyStatus::Lost])->count()],
            'topBooks' => $books, 'topCategories' => $categories, 'topStudents' => $students, 'trend' => $trend,
            'inventory' => collect(CopyStatus::cases())->map(fn (CopyStatus $status) => ['status' => $status->value, 'label' => $status->label(), 'total' => (int) ($inventory[$status->value] ?? 0)])->values(),
        ]);
    }
}
