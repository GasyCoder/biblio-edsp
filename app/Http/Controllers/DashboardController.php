<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ConsultationItem;
use App\Models\ConsultationSession;
use App\Models\Copy;
use App\Models\Loan;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $role = $user->getRoleNames()->first();
        $student = $role === 'etudiant' ? Student::query()->where('user_id', $user->id)->first() : null;

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'role' => $role,
                'metrics' => $this->metricsFor($role, $student),
                'quickActions' => $this->quickActionsFor($user),
                'alerts' => $this->alertsFor($role, $student),
                'traffic' => $this->trafficFor($student),
                'recentActivity' => $this->recentActivityFor($student),
                'inventory' => $this->inventorySummary(),
            ],
        ]);
    }

    /** @return list<array{label: string, value: int, icon: string, tone: string, detail: string}> */
    private function metricsFor(?string $role, ?Student $student): array
    {
        return match ($role) {
            'superadmin' => [
                ['label' => 'Comptes utilisateurs', 'value' => User::query()->count(), 'icon' => 'users', 'tone' => 'primary', 'detail' => 'Tous les comptes enregistrés'],
                ['label' => 'Étudiants', 'value' => Student::query()->count(), 'icon' => 'students', 'tone' => 'emerald', 'detail' => 'Dossiers étudiants enregistrés'],
                ['label' => 'Ouvrages', 'value' => Book::query()->count(), 'icon' => 'books', 'tone' => 'amber', 'detail' => 'Titres bibliographiques'],
                ['label' => 'Exemplaires', 'value' => Copy::query()->count(), 'icon' => 'copies', 'tone' => 'cyan', 'detail' => 'Copies physiques inventoriées'],
            ],
            'secretaire' => [
                ['label' => 'Entrées aujourd’hui', 'value' => Visit::query()->whereDate('checked_in_at', today())->count(), 'icon' => 'visits', 'tone' => 'primary', 'detail' => 'Fréquentation enregistrée ce jour'],
                ['label' => 'Présents maintenant', 'value' => Visit::query()->whereNull('checked_out_at')->count(), 'icon' => 'activity', 'tone' => 'emerald', 'detail' => 'Étudiants dans la bibliothèque'],
                ['label' => 'Consultations ouvertes', 'value' => ConsultationSession::query()->whereNull('closed_at')->count(), 'icon' => 'book-open', 'tone' => 'amber', 'detail' => 'Sessions de lecture sur place'],
                ['label' => 'Prêts en cours', 'value' => Loan::query()->whereNull('closed_at')->count(), 'icon' => 'loans', 'tone' => 'cyan', 'detail' => 'Prêts à domicile non clôturés'],
            ],
            'etudiant' => [
                ['label' => 'Présences', 'value' => $student?->visits()->count() ?? 0, 'icon' => 'visits', 'tone' => 'primary', 'detail' => 'Votre historique de fréquentation'],
                ['label' => 'Livres consultés', 'value' => $student ? ConsultationItem::query()->whereHas('session', fn ($query) => $query->where('student_id', $student->id))->count() : 0, 'icon' => 'book-open', 'tone' => 'emerald', 'detail' => 'Consultations sur place enregistrées'],
                ['label' => 'Ouvrages au catalogue', 'value' => Book::query()->count(), 'icon' => 'books', 'tone' => 'amber', 'detail' => 'Titres consultables'],
                ['label' => 'Prêts en cours', 'value' => $student?->loans()->whereNull('closed_at')->count() ?? 0, 'icon' => 'loans', 'tone' => 'cyan', 'detail' => 'Livres actuellement empruntés'],
            ],
            default => [],
        };
    }

    /** @return list<array{label: string, description: string, icon: string, permission: string, available: bool, href: ?string}> */
    private function quickActionsFor(User $user): array
    {
        $actions = [
            ['label' => 'Gérer les utilisateurs', 'description' => 'Comptes, rôles et accès', 'icon' => 'users', 'permission' => 'users.manage', 'available' => true, 'href' => route('users.index')],
            ['label' => 'Ouvrir le comptoir', 'description' => 'Scanner les cartes et les livres', 'icon' => 'scan', 'permission' => 'visits.check_in', 'available' => true, 'href' => route('desk.index')],
            ['label' => 'Rechercher un étudiant', 'description' => 'Matricule, carte ou identité', 'icon' => 'students', 'permission' => 'students.view', 'available' => true, 'href' => route('students.index')],
            ['label' => 'Gérer le catalogue', 'description' => 'Ouvrages et exemplaires', 'icon' => 'books', 'permission' => 'books.view', 'available' => true, 'href' => route('books.index')],
            ['label' => 'Consulter le catalogue', 'description' => 'Rechercher un ouvrage disponible', 'icon' => 'books', 'permission' => 'catalog.view', 'available' => true, 'href' => route('books.index')],
            ['label' => 'Voir les présences', 'description' => 'Historique et fréquentation', 'icon' => 'reports', 'permission' => 'visits.view_own', 'available' => true, 'href' => route('visits.index')],
        ];

        return array_values(array_filter(
            $actions,
            fn (array $action): bool => $user->can($action['permission']),
        ));
    }

    /** @return list<array{title: string, message: string, level: string}> */
    private function alertsFor(?string $role, ?Student $student): array
    {
        $overdue = Loan::query()->whereNull('closed_at')->whereDate('due_at', '<', today())
            ->when($student, fn ($query) => $query->where('student_id', $student->id))->count();

        if ($overdue > 0) {
            return [['title' => 'Retour en retard', 'message' => $overdue.' prêt(s) ont dépassé la date limite de retour.', 'level' => 'warning']];
        }

        return match ($role) {
            'superadmin', 'secretaire' => Visit::query()->whereNull('checked_out_at')->exists()
                ? [['title' => 'Bibliothèque en activité', 'message' => Visit::query()->whereNull('checked_out_at')->count().' étudiant(s) sont actuellement présents.', 'level' => 'success']]
                : [],
            'etudiant' => [],
            default => [],
        };
    }

    /** @return list<array{label: string, value: int}> */
    private function trafficFor(?Student $student): array
    {
        return collect(range(6, 0))->map(function (int $daysAgo) use ($student): array {
            $date = CarbonImmutable::today()->subDays($daysAgo);
            $value = Visit::query()->whereDate('checked_in_at', $date)
                ->when($student, fn ($query) => $query->where('student_id', $student->id))->count();

            return ['label' => ucfirst($date->locale('fr')->isoFormat('dd')), 'value' => $value];
        })->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentActivityFor(?Student $student): array
    {
        return Visit::query()->with('student:id,first_name,last_name,registration_number,photo_path')
            ->when($student, fn ($query) => $query->where('student_id', $student->id))
            ->latest('checked_in_at')->limit(6)->get()->map(fn (Visit $visit): array => [
                'id' => $visit->id,
                'student' => trim($visit->student->first_name.' '.$visit->student->last_name),
                'number' => $visit->student->registration_number,
                'photo' => $visit->student->photo_url,
                'checkedInAt' => $visit->checked_in_at?->format('d/m/Y H:i'),
                'status' => $visit->checked_out_at ? 'Terminée' : 'En cours',
                'active' => $visit->checked_out_at === null,
            ])->all();
    }

    /** @return array<string, int> */
    private function inventorySummary(): array
    {
        return [
            'available' => Copy::query()->where('status', 'available')->count(),
            'consultation' => Copy::query()->where('status', 'in_consultation')->count(),
            'borrowed' => Copy::query()->where('status', 'borrowed')->count(),
            'unavailable' => Copy::query()->whereIn('status', ['damaged', 'lost', 'archived'])->count(),
        ];
    }
}
