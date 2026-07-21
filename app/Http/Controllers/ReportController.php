<?php

namespace App\Http\Controllers;

use App\Enums\CopyStatus;
use App\Models\AcademicLevel;
use App\Models\AcademicMention;
use App\Models\AcademicProgram;
use App\Models\Book;
use App\Models\ConsultationItem;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\Student;
use App\Models\Visit;
use App\Services\AttendanceReport;
use App\Services\AttendanceScore;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    private const TABS = ['overview', 'attendance', 'presence', 'absences', 'documents'];

    public function __construct(private readonly AttendanceReport $attendance) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'granularity' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'group_by' => ['nullable', Rule::in(['level', 'mention', 'program'])],
            'level_id' => ['nullable', 'integer'],
            'mention_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,closed'],
            'never_only' => ['nullable', 'boolean'],
        ]);

        $tab = $validated['tab'] ?? 'overview';
        $filters = AttendanceReport::normalize($validated);
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();

        // Seul l'onglet actif est calculé : la page reste légère quel que soit le volume.
        $payload = match ($tab) {
            'attendance' => ['attendance' => $this->attendance->compute($filters)],
            'absences' => ['absences' => $this->attendance->absences(
                $filters,
                trim((string) ($validated['search'] ?? '')),
                (bool) ($validated['never_only'] ?? false),
            )],
            'documents' => ['documents' => $this->documents($from, $to)],
            'presence' => ['presence' => $this->presence($from, $to, $validated)],
            default => ['overview' => $this->overview($from, $to)],
        };

        return Inertia::render('Reports/Index', [
            'tab' => $tab,
            'filters' => $filters + [
                'search' => $validated['search'] ?? '',
                'status' => $validated['status'] ?? '',
                'never_only' => (bool) ($validated['never_only'] ?? false),
            ],
            'options' => [
                'levels' => AcademicLevel::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
                'mentions' => AcademicMention::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'programs' => AcademicProgram::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'years' => Student::query()->whereNotNull('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            ],
            'scoreWeights' => AttendanceScore::weights(),
            ...$payload,
        ]);
    }

    /**
     * Export tableau (liste brute) de l'onglet courant : Présences ou Absences.
     * L'onglet Assiduité garde son export analytique dédié.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $table = $this->table($request);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($table['sheet']);
        $sheet->fromArray($table['columns'], null, 'A1');
        $sheet->getStyle('A1:'.$this->columnLetter(count($table['columns'])).'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        foreach ($table['rows'] as $index => $row) {
            $sheet->fromArray(array_values($row), null, 'A'.($index + 2));
        }
        foreach (range(1, count($table['columns'])) as $position) {
            $sheet->getColumnDimension($this->columnLetter($position))->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), $table['file'].'-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $table['file'].'-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        $table = $this->table($request);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml(view('reports.table', $table + ['embedded' => true])->render());
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$table['file'].'-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }

    public function print(Request $request): View
    {
        return view('reports.table', $this->table($request));
    }

    /**
     * Construit le tableau à plat correspondant à l'onglet demandé.
     *
     * @return array<string, mixed>
     */
    private function table(Request $request): array
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', Rule::in(['level', 'mention', 'program'])],
            'level_id' => ['nullable', 'integer'],
            'mention_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'academic_year' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,closed'],
            'never_only' => ['nullable', 'boolean'],
        ]);

        $filters = AttendanceReport::normalize($validated);
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $period = 'Période du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y');
        $groupLabel = $this->attendance->groupByLabel($filters['group_by']);

        if (($validated['tab'] ?? null) === 'absences') {
            $rows = $this->attendance->absenceRows(
                $filters,
                trim((string) ($validated['search'] ?? '')),
                (bool) ($validated['never_only'] ?? false),
            );

            return [
                'title' => 'Liste des absences',
                'subtitle' => $period,
                'context' => 'Jours d’absence = jours d’ouverture − jours de présence. Regroupement : '.$groupLabel.'.',
                'sheet' => 'Absences',
                'file' => 'absences-edsp',
                'columns' => ['N° bibliothèque', 'Nom et prénoms', $groupLabel, 'Jours présents', 'Jours d’absence', 'Taux d’absence', 'Statut'],
                'numeric' => [3, 4, 5],
                'rows' => $rows->map(fn (array $row) => [
                    $row['registration_number'],
                    $row['name'],
                    $row['group'],
                    $row['daysPresent'],
                    $row['absenceDays'],
                    $row['absenceRate'].' %',
                    $row['neverCame'] ? 'Jamais venu' : 'Venu partiellement',
                ])->all(),
            ];
        }

        $visits = $this->presenceQuery($from, $to, $validated)->get();

        return [
            'title' => 'Liste des présences',
            'subtitle' => $period,
            'context' => 'Registre des passages enregistrés au comptoir.',
            'sheet' => 'Présences',
            'file' => 'presences-edsp',
            'columns' => ['N° passage', 'N° bibliothèque', 'Matricule', 'Nom et prénoms', 'Entrée', 'Sortie', 'Durée', 'Consultations', 'Statut'],
            'numeric' => [7],
            'rows' => $visits->map(fn (Visit $visit) => [
                $visit->visit_number,
                $visit->student?->registration_number,
                $visit->student?->academic_number ?: '—',
                trim(($visit->student?->last_name ?? '').' '.($visit->student?->first_name ?? '')),
                $visit->checked_in_at?->format('d/m/Y H:i'),
                $visit->checked_out_at?->format('d/m/Y H:i') ?: '—',
                $this->duration($visit),
                $visit->consultation_sessions_count,
                $visit->checked_out_at ? 'Sorti' : 'Présent',
            ])->all(),
        ];
    }

    private function duration(Visit $visit): string
    {
        if (! $visit->checked_in_at || ! $visit->checked_out_at) {
            return '—';
        }
        $minutes = max(0, $visit->checked_in_at->diffInMinutes($visit->checked_out_at));

        return $minutes < 60 ? $minutes.' min' : intdiv($minutes, 60).' h '.($minutes % 60).' min';
    }

    private function columnLetter(int $position): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($position);
    }

    /** @return array<string, mixed> */
    private function overview(Carbon $from, Carbon $to): array
    {
        $visits = Visit::query()->whereBetween('checked_in_at', [$from, $to]);

        $dailyVisits = Visit::query()->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('date(checked_in_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');
        $dailyConsultations = ConsultationItem::query()->whereBetween('scanned_at', [$from, $to])
            ->selectRaw('date(scanned_at) as day, count(*) as total')->groupBy('day')->pluck('total', 'day');

        $trend = collect(range(0, $from->diffInDays($to)))->map(function (int $offset) use ($from, $dailyVisits, $dailyConsultations) {
            $day = $from->copy()->addDays($offset)->toDateString();

            return ['day' => $day, 'visits' => (int) ($dailyVisits[$day] ?? 0), 'consultations' => (int) ($dailyConsultations[$day] ?? 0)];
        });

        return [
            'metrics' => [
                'visits' => (clone $visits)->count(),
                'uniqueStudents' => (clone $visits)->distinct()->count('student_id'),
                'consultations' => ConsultationItem::query()->whereBetween('scanned_at', [$from, $to])->count(),
                'loans' => Loan::query()->whereBetween('opened_at', [$from, $to])->count(),
            ],
            'alerts' => [
                'present' => Visit::query()->whereNull('checked_out_at')->count(),
                'overdueLoans' => Loan::query()->whereNull('closed_at')->where('due_at', '<', now())->count(),
                'unavailableCopies' => Copy::query()->whereIn('status', [CopyStatus::Damaged, CopyStatus::Lost])->count(),
            ],
            'trend' => $trend,
            'topStudents' => Student::query()->withCount([
                'visits' => fn (Builder $query) => $query->whereBetween('checked_in_at', [$from, $to]),
                'consultationSessions' => fn (Builder $query) => $query->whereBetween('opened_at', [$from, $to]),
                'loans' => fn (Builder $query) => $query->whereBetween('opened_at', [$from, $to]),
            ])->get(['id', 'registration_number', 'last_name', 'first_name', 'photo_path'])
                ->map(function (Student $student) {
                    $student->activity_total = $student->visits_count + $student->consultation_sessions_count + $student->loans_count;

                    return $student;
                })->filter(fn (Student $student) => $student->activity_total > 0)
                ->sortByDesc('activity_total')->take(8)->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function documents(Carbon $from, Carbon $to): array
    {
        $consultedByBook = DB::table('consultation_items')->join('copies', 'copies.id', '=', 'consultation_items.copy_id')
            ->whereBetween('consultation_items.scanned_at', [$from, $to])->groupBy('copies.book_id')
            ->selectRaw('copies.book_id, count(*) as total')->pluck('total', 'book_id');
        $loanedByBook = DB::table('loan_items')->join('copies', 'copies.id', '=', 'loan_items.copy_id')
            ->whereBetween('loan_items.loaned_at', [$from, $to])->groupBy('copies.book_id')
            ->selectRaw('copies.book_id, count(*) as total')->pluck('total', 'book_id');
        $bookIds = $consultedByBook->keys()->merge($loanedByBook->keys())->unique();

        $books = Book::query()->whereKey($bookIds)->get(['id', 'title', 'cover_path'])
            ->map(function (Book $book) use ($consultedByBook, $loanedByBook) {
                $book->consultations = (int) ($consultedByBook[$book->id] ?? 0);
                $book->loans = (int) ($loanedByBook[$book->id] ?? 0);
                $book->total = $book->consultations + $book->loans;

                return $book;
            })->sortByDesc('total')->take(12)->values();

        $categories = DB::table('consultation_items')
            ->join('copies', 'copies.id', '=', 'consultation_items.copy_id')
            ->join('books', 'books.id', '=', 'copies.book_id')
            ->leftJoin('categories', 'categories.id', '=', 'books.category_id')
            ->whereBetween('consultation_items.scanned_at', [$from, $to])
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw("coalesce(categories.name, 'Sans catégorie') as name, count(*) as total")
            ->orderByDesc('total')->limit(8)->get();

        $inventory = Copy::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status');

        return [
            'topBooks' => $books,
            'topCategories' => $categories,
            'inventory' => collect(CopyStatus::cases())->map(fn (CopyStatus $status) => [
                'status' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($inventory[$status->value] ?? 0),
            ])->values(),
        ];
    }

    /**
     * Présences : registre détaillé des passages, paginé — reprend le contenu
     * de la page /visits. Calculé seulement quand l'onglet est ouvert.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    /**
     * Requête des passages, partagée par l'affichage paginé et les exports.
     *
     * @param  array<string, mixed>  $validated
     * @return Builder<Visit>
     */
    private function presenceQuery(Carbon $from, Carbon $to, array $validated): Builder
    {
        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? '';

        return Visit::query()
            ->whereBetween('checked_in_at', [$from, $to])
            ->when($status === 'active', fn (Builder $query) => $query->whereNull('checked_out_at'))
            ->when($status === 'closed', fn (Builder $query) => $query->whereNotNull('checked_out_at'))
            ->when($search, fn (Builder $query) => $query->where(function (Builder $nested) use ($search) {
                $nested->where('visit_number', 'like', "%{$search}%")
                    ->orWhereHas('student', fn (Builder $student) => $student
                        ->where('registration_number', 'like', "%{$search}%")
                        ->orWhere('academic_number', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%"));
            }))
            ->with(['student:id,registration_number,academic_number,last_name,first_name,photo_path', 'checkedInBy:id,name', 'checkedOutBy:id,name'])
            ->withCount('consultationSessions')
            ->latest('checked_in_at');
    }

    private function presence(Carbon $from, Carbon $to, array $validated): array
    {
        $visits = $this->presenceQuery($from, $to, $validated)->paginate(50)->withQueryString();

        $visits->getCollection()->transform(fn (Visit $visit) => [
            'id' => $visit->id,
            'visit_number' => $visit->visit_number,
            'checked_in_at' => $visit->checked_in_at?->toIso8601String(),
            'checked_out_at' => $visit->checked_out_at?->toIso8601String(),
            'consultations_count' => $visit->consultation_sessions_count,
            'checked_in_by' => $visit->checkedInBy?->name,
            'checked_out_by' => $visit->checkedOutBy?->name,
            'student' => $visit->student,
        ]);

        return [
            'stats' => [
                'openNow' => Visit::query()->whereNull('checked_out_at')->count(),
                'today' => Visit::query()->whereDate('checked_in_at', today())->count(),
                'closedToday' => Visit::query()->whereDate('checked_out_at', today())->count(),
                'periodTotal' => Visit::query()->whereBetween('checked_in_at', [$from, $to])->count(),
                'uniqueStudents' => Visit::query()->whereBetween('checked_in_at', [$from, $to])->distinct()->count('student_id'),
            ],
            'visits' => $visits,
        ];
    }
}
