<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class VisitController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,closed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $user = $request->user();
        $ownOnly = $user->hasRole('etudiant');
        $studentId = $ownOnly ? $user->student?->id : null;

        $scope = fn (): Builder => Visit::query()
            ->when($ownOnly, fn (Builder $query) => $query->where('student_id', $studentId ?? 0));

        $search = trim((string) ($filters['search'] ?? ''));
        $visitFilter = fn ($query) => $query
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $builder) => $builder->whereNull('checked_out_at'))
            ->when(($filters['status'] ?? null) === 'closed', fn (Builder $builder) => $builder->whereNotNull('checked_out_at'))
            ->when($filters['from'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('checked_in_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('checked_in_at', '<=', $date));

        $students = Student::query()
            ->when($ownOnly, fn (Builder $query) => $query->whereKey($studentId ?? 0))
            ->whereHas('visits', $visitFilter)
            ->when($search, fn (Builder $query) => $query->where(function (Builder $nested) use ($search) {
                $nested->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('academic_number', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereHas('visits', fn (Builder $visits) => $visits->where('visit_number', 'like', "%{$search}%"));
            }))
            ->with(['visits' => fn ($query) => $visitFilter($query)
                ->with(['checkedInBy:id,name,email', 'checkedInBy.roles:id,name', 'checkedOutBy:id,name,email', 'checkedOutBy.roles:id,name'])
                ->withCount('consultationSessions')
                ->latest('checked_in_at')])
            ->withCount([
                'visits as active_visits_count' => fn (Builder $query) => $query->whereNull('checked_out_at'),
                'visits as matching_visits_count' => $visitFilter,
            ])
            ->withMax(['visits as latest_visit_at' => $visitFilter], 'checked_in_at')
            ->orderByDesc('active_visits_count')
            ->orderByDesc('latest_visit_at');

        return Inertia::render('Visits/Index', [
            'studentGroups' => $students->paginate(20)->withQueryString(),
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'stats' => [
                'active' => $scope()->whereNull('checked_out_at')->count(),
                'today' => $scope()->whereDate('checked_in_at', today())->count(),
                'closedToday' => $scope()->whereDate('checked_out_at', today())->count(),
                'total' => $scope()->count(),
            ],
            'ownOnly' => $ownOnly,
        ]);
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$visits] = $this->reportData($request);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Présences');
        $sheet->fromArray(['N° présence', 'N° bibliothèque', 'Matricule', 'Nom et prénoms', 'Entrée', 'Accueil entrée', 'Sortie', 'Accueil sortie', 'Durée', 'Statut', 'Consultations'], null, 'A1');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        foreach ($visits as $index => $visit) {
            $sheet->fromArray([
                $visit->visit_number,
                $visit->student->registration_number,
                $visit->student->academic_number,
                "{$visit->student->last_name} {$visit->student->first_name}",
                $visit->checked_in_at?->format('d/m/Y H:i:s'),
                $this->operatorLabel($visit->checkedInBy, $visit->checked_in_role),
                $visit->checked_out_at?->format('d/m/Y H:i:s'),
                $visit->checked_out_at ? $this->operatorLabel($visit->checkedOutBy, $visit->checked_out_role) : null,
                $this->durationLabel($visit),
                $visit->checked_out_at ? 'Sorti' : 'Présent',
                $visit->consultation_sessions_count,
            ], null, 'A'.($index + 2));
        }
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = tempnam(sys_get_temp_dir(), 'presences-edsp-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'presences-edsp-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        [$visits, $filters] = $this->reportData($request);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml(view('reports.visits', compact('visits', 'filters'))->render());
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="presences-edsp-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }

    public function print(Request $request): View
    {
        [$visits, $filters] = $this->reportData($request);

        return view('reports.visits', compact('visits', 'filters'));
    }

    /** @return array{0: Collection, 1: array<string, string>} */
    private function reportData(Request $request): array
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,closed'],
            'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $visits = Visit::query()->with([
            'student:id,registration_number,academic_number,last_name,first_name',
            'checkedInBy:id,name,email',
            'checkedInBy.roles:id,name',
            'checkedOutBy:id,name,email',
            'checkedOutBy.roles:id,name',
        ])
            ->withCount('consultationSessions')
            ->when($search, fn (Builder $query) => $query->where(function (Builder $nested) use ($search) {
                $nested->where('visit_number', 'like', "%{$search}%")->orWhereHas('student', fn (Builder $student) => $student
                    ->where('registration_number', 'like', "%{$search}%")->orWhere('academic_number', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%"));
            }))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->whereNull('checked_out_at'))
            ->when(($filters['status'] ?? null) === 'closed', fn (Builder $query) => $query->whereNotNull('checked_out_at'))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('checked_in_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('checked_in_at', '<=', $date))
            ->latest('checked_in_at')->get();

        return [$visits, ['search' => $search, 'status' => $filters['status'] ?? '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? '']];
    }

    private function durationLabel(Visit $visit): string
    {
        $minutes = (int) $visit->checked_in_at->diffInMinutes($visit->checked_out_at ?? now());

        return intdiv($minutes, 60).' h '.($minutes % 60).' min';
    }

    private function operatorLabel(?User $operator, ?string $role): string
    {
        $role ??= $operator?->roles->first()?->name;

        return $role === 'superadmin' ? 'SuperAdmin' : ($operator?->name ?: 'Compte inconnu');
    }
}
