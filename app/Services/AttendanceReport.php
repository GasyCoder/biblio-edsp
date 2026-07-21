<?php

namespace App\Services;

use App\Models\ConsultationSession;
use App\Models\Loan;
use App\Models\Student;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Calcul du rapport d'assiduité, partagé par l'onglet « Assiduité » de la page
 * Rapports et par les exports Excel/PDF.
 *
 * Modèle retenu : fréquentation réelle. Un étudiant est « présent » un jour dès
 * qu'un passage est enregistré ce jour-là ; « absent » = inscrit actif venu 0 fois.
 */
class AttendanceReport
{
    /**
     * Normalise les filtres validés en appliquant les valeurs par défaut.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function normalize(array $validated): array
    {
        return [
            'from' => Carbon::parse($validated['from'] ?? now()->subDays(29)->toDateString())->toDateString(),
            'to' => Carbon::parse($validated['to'] ?? now()->toDateString())->toDateString(),
            'granularity' => $validated['granularity'] ?? 'day',
            'group_by' => $validated['group_by'] ?? 'level',
            'level_id' => $validated['level_id'] ?? null,
            'mention_id' => $validated['mention_id'] ?? null,
            'program_id' => $validated['program_id'] ?? null,
            'academic_year' => $validated['academic_year'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters  filtres déjà normalisés
     * @return array<string, mixed>
     */
    public function compute(array $filters): array
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $granularity = $filters['granularity'];
        $groupBy = $filters['group_by'];

        $cohort = Student::query()
            ->where('status', 'active')
            ->when($filters['level_id'], fn ($query) => $query->where('level_id', $filters['level_id']))
            ->when($filters['mention_id'], fn ($query) => $query->where('mention_id', $filters['mention_id']))
            ->when($filters['program_id'], fn ($query) => $query->where('program_id', $filters['program_id']))
            ->when($filters['academic_year'], fn ($query) => $query->where('academic_year', $filters['academic_year']))
            ->with(['academicLevel:id,name', 'mention:id,name', 'academicProgram:id,name'])
            ->get(['id', 'registration_number', 'last_name', 'first_name', 'photo_path', 'level_id', 'mention_id', 'program_id', 'academic_year']);
        $cohortIds = $cohort->pluck('id');

        $daysPresent = Visit::query()
            ->whereIn('student_id', $cohortIds)
            ->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('student_id, count(distinct date(checked_in_at)) as days')
            ->groupBy('student_id')
            ->pluck('days', 'student_id');

        $consultations = ConsultationSession::query()
            ->whereIn('student_id', $cohortIds)
            ->whereBetween('opened_at', [$from, $to])
            ->selectRaw('student_id, count(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $loans = Loan::query()
            ->whereIn('student_id', $cohortIds)
            ->whereBetween('opened_at', [$from, $to])
            ->selectRaw('student_id, count(*) as total')
            ->groupBy('student_id')
            ->pluck('total', 'student_id');

        $pairs = Visit::query()
            ->whereIn('student_id', $cohortIds)
            ->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('student_id, date(checked_in_at) as day')
            ->distinct()
            ->get();

        $openDays = (int) Visit::query()
            ->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('count(distinct date(checked_in_at)) as total')
            ->value('total');

        $cohortCount = $cohort->count();
        $present = $daysPresent->keys()->count();
        $totalPresenceDays = (int) $daysPresent->sum();

        return [
            'kpis' => [
                'cohort' => $cohortCount,
                'present' => $present,
                'absent' => $cohortCount - $present,
                'attendanceRate' => $cohortCount ? (int) round($present / $cohortCount * 100) : 0,
                'openDays' => $openDays,
                'totalPresenceDays' => $totalPresenceDays,
                'avgDaysPerPresent' => $present ? round($totalPresenceDays / $present, 1) : 0,
                'totalVisits' => (int) Visit::query()->whereIn('student_id', $cohortIds)->whereBetween('checked_in_at', [$from, $to])->count(),
            ],
            'trend' => $this->trend($pairs, $from, $to, $granularity),
            'breakdown' => $this->breakdown($cohort, $daysPresent, $groupBy),
            'ranking' => $this->ranking($cohort, $daysPresent, $consultations, $loans, $groupBy),
            'absentees' => $this->absentees($cohort, $daysPresent, $groupBy),
        ];
    }

    /**
     * Détail des absences par étudiant, paginé et cherchable.
     *
     * Deux notions cohabitent volontairement :
     *  - « jamais venu » : aucun passage sur la période (absence totale) ;
     *  - « jours d'absence » : jours d'ouverture - jours de présence, y compris
     *    pour un étudiant venu une seule fois.
     *
     * @param  array<string, mixed>  $filters  filtres déjà normalisés
     * @return array<string, mixed>
     */
    public function absences(array $filters, string $search = '', bool $neverOnly = false, int $perPage = 50): array
    {
        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->endOfDay();
        $groupBy = $filters['group_by'];

        $cohort = Student::query()
            ->where('status', 'active')
            ->when($filters['level_id'], fn ($query) => $query->where('level_id', $filters['level_id']))
            ->when($filters['mention_id'], fn ($query) => $query->where('mention_id', $filters['mention_id']))
            ->when($filters['program_id'], fn ($query) => $query->where('program_id', $filters['program_id']))
            ->when($filters['academic_year'], fn ($query) => $query->where('academic_year', $filters['academic_year']))
            ->when($search !== '', fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('academic_number', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%");
            }))
            ->with(['academicLevel:id,name', 'mention:id,name', 'academicProgram:id,name'])
            ->get(['id', 'registration_number', 'last_name', 'first_name', 'photo_path', 'level_id', 'mention_id', 'program_id']);

        $daysPresent = Visit::query()
            ->whereIn('student_id', $cohort->pluck('id'))
            ->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('student_id, count(distinct date(checked_in_at)) as days')
            ->groupBy('student_id')
            ->pluck('days', 'student_id');

        $openDays = (int) Visit::query()
            ->whereBetween('checked_in_at', [$from, $to])
            ->selectRaw('count(distinct date(checked_in_at)) as total')
            ->value('total');

        $rows = $cohort
            ->map(function (Student $student) use ($daysPresent, $openDays, $groupBy) {
                $present = (int) ($daysPresent[$student->id] ?? 0);
                $absenceDays = max(0, $openDays - $present);

                return [
                    'id' => $student->id,
                    'registration_number' => $student->registration_number,
                    'name' => trim($student->last_name.' '.$student->first_name),
                    'photo_url' => $student->photo_url,
                    'group' => $this->groupLabel($student, $groupBy),
                    'daysPresent' => $present,
                    'absenceDays' => $absenceDays,
                    'neverCame' => $present === 0,
                    'absenceRate' => $openDays ? (int) round($absenceDays / $openDays * 100) : 0,
                ];
            })
            ->when($neverOnly, fn (Collection $items) => $items->filter(fn (array $row) => $row['neverCame']))
            ->sortByDesc('absenceDays')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );

        return [
            'openDays' => $openDays,
            'neverCame' => $rows->filter(fn (array $row) => $row['neverCame'])->count(),
            'students' => $paginator->withQueryString(),
        ];
    }

    public function granularityLabel(string $granularity): string
    {
        return match ($granularity) {
            'week' => 'Par semaine',
            'month' => 'Par mois',
            default => 'Par jour',
        };
    }

    public function groupByLabel(string $groupBy): string
    {
        return match ($groupBy) {
            'mention' => 'Mention',
            'program' => 'Parcours',
            default => 'Niveau',
        };
    }

    /**
     * @param  Collection<int, object>  $pairs
     * @return Collection<int, array<string, mixed>>
     */
    private function trend(Collection $pairs, Carbon $from, Carbon $to, string $granularity): Collection
    {
        $grouped = $pairs->groupBy(fn ($pair) => $this->bucketKey($pair->day, $granularity));

        return collect($this->buckets($from, $to, $granularity))->map(function (array $bucket) use ($grouped) {
            $rows = $grouped->get($bucket['key']);

            return [
                'label' => $bucket['label'],
                'present' => $rows ? $rows->pluck('student_id')->unique()->count() : 0,
                'presenceDays' => $rows ? $rows->count() : 0,
            ];
        });
    }

    /** @return array<int, array{key: string, label: string}> */
    private function buckets(Carbon $from, Carbon $to, string $granularity): array
    {
        $list = [];

        if ($granularity === 'week') {
            $cursor = $from->copy()->startOfWeek();
            while ($cursor <= $to) {
                $list[] = ['key' => $cursor->isoFormat('GGGG-[W]WW'), 'label' => 'Sem. '.$cursor->isoWeek().' · '.$cursor->isoFormat('DD/MM')];
                $cursor->addWeek();
            }
        } elseif ($granularity === 'month') {
            $cursor = $from->copy()->startOfMonth();
            while ($cursor <= $to) {
                $list[] = ['key' => $cursor->format('Y-m'), 'label' => ucfirst($cursor->isoFormat('MMMM YYYY'))];
                $cursor->addMonth();
            }
        } else {
            $cursor = $from->copy()->startOfDay();
            while ($cursor <= $to) {
                $list[] = ['key' => $cursor->toDateString(), 'label' => $cursor->isoFormat('ddd DD/MM')];
                $cursor->addDay();
            }
        }

        return $list;
    }

    private function bucketKey(string $day, string $granularity): string
    {
        $date = Carbon::parse($day);

        return match ($granularity) {
            'week' => $date->isoFormat('GGGG-[W]WW'),
            'month' => $date->format('Y-m'),
            default => $date->toDateString(),
        };
    }

    /**
     * @param  Collection<int, Student>  $cohort
     * @return Collection<int, array<string, mixed>>
     */
    private function breakdown(Collection $cohort, Collection $daysPresent, string $groupBy): Collection
    {
        return $cohort
            ->groupBy(fn (Student $student) => $this->groupLabel($student, $groupBy))
            ->map(function (Collection $members, string $label) use ($daysPresent) {
                $count = $members->count();
                $present = $members->filter(fn (Student $s) => (int) ($daysPresent[$s->id] ?? 0) > 0)->count();
                $presenceDays = $members->sum(fn (Student $s) => (int) ($daysPresent[$s->id] ?? 0));

                return [
                    'label' => $label,
                    'cohort' => $count,
                    'present' => $present,
                    'absent' => $count - $present,
                    'rate' => $count ? (int) round($present / $count * 100) : 0,
                    'presenceDays' => $presenceDays,
                    'avgDays' => $present ? round($presenceDays / $present, 1) : 0,
                ];
            })
            ->sortByDesc('present')
            ->values();
    }

    /**
     * @param  Collection<int, Student>  $cohort
     * @return Collection<int, array<string, mixed>>
     */
    private function ranking(Collection $cohort, Collection $daysPresent, Collection $consultations, Collection $loans, string $groupBy): Collection
    {
        return $cohort
            ->map(function (Student $student) use ($daysPresent, $consultations, $loans, $groupBy) {
                $days = (int) ($daysPresent[$student->id] ?? 0);
                $consults = (int) ($consultations[$student->id] ?? 0);
                $borrowed = (int) ($loans[$student->id] ?? 0);

                return [
                    'id' => $student->id,
                    'registration_number' => $student->registration_number,
                    'name' => trim($student->last_name.' '.$student->first_name),
                    'photo_url' => $student->photo_url,
                    'group' => $this->groupLabel($student, $groupBy),
                    'daysPresent' => $days,
                    'consultations' => $consults,
                    'loans' => $borrowed,
                    'score' => AttendanceScore::compute($days, $consults, $borrowed),
                ];
            })
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take(20)
            ->values();
    }

    /**
     * @param  Collection<int, Student>  $cohort
     * @return Collection<int, array<string, mixed>>
     */
    private function absentees(Collection $cohort, Collection $daysPresent, string $groupBy): Collection
    {
        return $cohort
            ->filter(fn (Student $student) => (int) ($daysPresent[$student->id] ?? 0) === 0)
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'registration_number' => $student->registration_number,
                'name' => trim($student->last_name.' '.$student->first_name),
                'photo_url' => $student->photo_url,
                'group' => $this->groupLabel($student, $groupBy),
            ])
            ->sortBy('name')
            ->values();
    }

    private function groupLabel(Student $student, string $groupBy): string
    {
        return match ($groupBy) {
            'mention' => $student->mention?->name ?? 'Sans mention',
            'program' => $student->academicProgram?->name ?? 'Sans parcours',
            default => $student->academicLevel?->name ?? ($student->level ?: 'Sans niveau'),
        };
    }
}
