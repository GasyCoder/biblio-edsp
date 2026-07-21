<?php

namespace App\Http\Controllers;

use App\Services\AttendanceReport;
use App\Services\AttendanceScore;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AttendanceReportController extends Controller
{
    public function __construct(private readonly AttendanceReport $report) {}

    /**
     * L'assiduité vit désormais dans l'onglet dédié de la page Rapports.
     * On redirige pour ne pas casser les liens et favoris existants.
     */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('reports.index', ['tab' => 'attendance'] + $request->query());
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        [$filters, $data] = $this->data($request);
        $spreadsheet = new Spreadsheet;

        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Synthèse');
        $summary->fromArray([['Rapport d’assiduité']], null, 'A1');
        $summary->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summary->fromArray([
            ['Période', $filters['from'].' → '.$filters['to']],
            ['Granularité', $this->report->granularityLabel($filters['granularity'])],
            ['Regroupement', $this->report->groupByLabel($filters['group_by'])],
            [],
            ['Effectif suivi', $data['kpis']['cohort']],
            ['Présents', $data['kpis']['present']],
            ['Absents', $data['kpis']['absent']],
            ['Taux de fréquentation (%)', $data['kpis']['attendanceRate']],
            ['Passages enregistrés', $data['kpis']['totalVisits']],
            ['Jours de présence cumulés', $data['kpis']['totalPresenceDays']],
            ['Jours de présence moyens / présent', $data['kpis']['avgDaysPerPresent']],
            ['Jours d’ouverture', $data['kpis']['openDays']],
        ], null, 'A3');
        $summary->getColumnDimension('A')->setAutoSize(true);
        $summary->getColumnDimension('B')->setAutoSize(true);

        $groupLabel = $this->report->groupByLabel($filters['group_by']);
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Par '.mb_strtolower($groupLabel));
        $sheet->fromArray([$groupLabel, 'Effectif', 'Présents', 'Absents', 'Taux (%)', 'Jours de présence', 'Jours moyens'], null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        foreach ($data['breakdown'] as $index => $row) {
            $sheet->fromArray([$row['label'], $row['cohort'], $row['present'], $row['absent'], $row['rate'], $row['presenceDays'], $row['avgDays']], null, 'A'.($index + 2));
        }
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $rank = $spreadsheet->createSheet();
        $rank->setTitle('Classement');
        $rank->fromArray(['Rang', 'N° bibliothèque', 'Nom et prénoms', $groupLabel, 'Jours présents', 'Consultations', 'Prêts', 'Score'], null, 'A1');
        $rank->getStyle('A1:H1')->getFont()->setBold(true);
        foreach ($data['ranking'] as $index => $row) {
            $rank->fromArray([$index + 1, $row['registration_number'], $row['name'], $row['group'], $row['daysPresent'], $row['consultations'], $row['loans'], $row['score']], null, 'A'.($index + 2));
        }
        foreach (range('A', 'H') as $column) {
            $rank->getColumnDimension($column)->setAutoSize(true);
        }

        $absent = $spreadsheet->createSheet();
        $absent->setTitle('Absents');
        $absent->fromArray(['N° bibliothèque', 'Nom et prénoms', $groupLabel], null, 'A1');
        $absent->getStyle('A1:C1')->getFont()->setBold(true);
        foreach ($data['absentees']->values() as $index => $row) {
            $absent->fromArray([$row['registration_number'], $row['name'], $row['group']], null, 'A'.($index + 2));
        }
        foreach (range('A', 'C') as $column) {
            $absent->getColumnDimension($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'assiduite-edsp-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, 'assiduite-edsp-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend(true);
    }

    public function exportPdf(Request $request): HttpResponse
    {
        [$filters, $data] = $this->data($request);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml(view('reports.attendance', [
            'filters' => $filters,
            'kpis' => $data['kpis'],
            'breakdown' => $data['breakdown'],
            'ranking' => $data['ranking'],
            'absentees' => $data['absentees'],
            'groupLabel' => $this->report->groupByLabel($filters['group_by']),
            'granularityLabel' => $this->report->granularityLabel($filters['granularity']),
            'weights' => AttendanceScore::weights(),
        ])->render());
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="assiduite-edsp-'.now()->format('Ymd-His').'.pdf"',
        ]);
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function data(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'granularity' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'group_by' => ['nullable', Rule::in(['level', 'mention', 'program'])],
            'level_id' => ['nullable', 'integer'],
            'mention_id' => ['nullable', 'integer'],
            'program_id' => ['nullable', 'integer'],
            'academic_year' => ['nullable', 'string', 'max:20'],
        ]);
        $filters = AttendanceReport::normalize($validated);

        return [$filters, $this->report->compute($filters)];
    }
}
