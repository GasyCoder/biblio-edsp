<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Copy;
use App\Models\Student;
use App\Models\User;
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

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'role' => $role,
                'metrics' => $this->metricsFor($role),
                'quickActions' => $this->quickActionsFor($user),
                'alerts' => $this->alertsFor($role),
            ],
        ]);
    }

    /** @return list<array{label: string, value: int, icon: string, tone: string, detail: string}> */
    private function metricsFor(?string $role): array
    {
        return match ($role) {
            'superadmin' => [
                ['label' => 'Comptes utilisateurs', 'value' => User::query()->count(), 'icon' => 'users', 'tone' => 'primary', 'detail' => 'Tous les comptes enregistrés'],
                ['label' => 'Étudiants', 'value' => Student::query()->count(), 'icon' => 'students', 'tone' => 'emerald', 'detail' => 'Dossiers étudiants enregistrés'],
                ['label' => 'Ouvrages', 'value' => Book::query()->count(), 'icon' => 'books', 'tone' => 'amber', 'detail' => 'Titres bibliographiques'],
                ['label' => 'Exemplaires', 'value' => Copy::query()->count(), 'icon' => 'copies', 'tone' => 'cyan', 'detail' => 'Copies physiques inventoriées'],
            ],
            'secretaire' => [
                ['label' => 'Entrées aujourd’hui', 'value' => 0, 'icon' => 'visits', 'tone' => 'primary', 'detail' => 'Module de pointage à initialiser'],
                ['label' => 'Consultations ouvertes', 'value' => 0, 'icon' => 'book-open', 'tone' => 'emerald', 'detail' => 'Aucune session active'],
                ['label' => 'Étudiants', 'value' => Student::query()->count(), 'icon' => 'students', 'tone' => 'amber', 'detail' => 'Dossiers consultables'],
                ['label' => 'Ouvrages', 'value' => Book::query()->count(), 'icon' => 'books', 'tone' => 'cyan', 'detail' => 'Titres au catalogue'],
            ],
            'etudiant' => [
                ['label' => 'Présences', 'value' => 0, 'icon' => 'visits', 'tone' => 'primary', 'detail' => 'Votre historique personnel'],
                ['label' => 'Livres consultés', 'value' => 0, 'icon' => 'book-open', 'tone' => 'emerald', 'detail' => 'Consultations sur place'],
                ['label' => 'Ouvrages au catalogue', 'value' => Book::query()->count(), 'icon' => 'books', 'tone' => 'amber', 'detail' => 'Titres consultables'],
                ['label' => 'Exemplaires disponibles', 'value' => Copy::query()->where('status', 'available')->count(), 'icon' => 'copies', 'tone' => 'cyan', 'detail' => 'Disponibilité actuelle'],
            ],
            default => [],
        };
    }

    /** @return list<array{label: string, description: string, icon: string, permission: string, available: bool, href: ?string}> */
    private function quickActionsFor(User $user): array
    {
        $actions = [
            ['label' => 'Gérer les utilisateurs', 'description' => 'Comptes, rôles et accès', 'icon' => 'users', 'permission' => 'users.manage', 'available' => false, 'href' => null],
            ['label' => 'Scanner une carte', 'description' => 'Entrée ou sortie étudiant', 'icon' => 'scan', 'permission' => 'visits.check_in', 'available' => false, 'href' => null],
            ['label' => 'Rechercher un étudiant', 'description' => 'Matricule, carte ou identité', 'icon' => 'students', 'permission' => 'students.view', 'available' => true, 'href' => route('students.index')],
            ['label' => 'Gérer le catalogue', 'description' => 'Ouvrages et exemplaires', 'icon' => 'books', 'permission' => 'books.view', 'available' => true, 'href' => route('books.index')],
            ['label' => 'Consulter le catalogue', 'description' => 'Rechercher un ouvrage disponible', 'icon' => 'books', 'permission' => 'catalog.view', 'available' => true, 'href' => route('books.index')],
            ['label' => 'Mon historique', 'description' => 'Présences et lectures sur place', 'icon' => 'reports', 'permission' => 'visits.view_own', 'available' => false, 'href' => null],
        ];

        return array_values(array_filter(
            $actions,
            fn (array $action): bool => $user->can($action['permission']),
        ));
    }

    /** @return list<array{title: string, message: string, level: string}> */
    private function alertsFor(?string $role): array
    {
        return match ($role) {
            'superadmin' => [['title' => 'Configuration en cours', 'message' => 'Le socle utilisateurs et permissions est actif. Les référentiels métier constituent la prochaine étape.', 'level' => 'info']],
            'secretaire' => [['title' => 'Poste opérationnel prêt', 'message' => 'Les fonctions de scan seront activées après la création des référentiels étudiants et ouvrages.', 'level' => 'info']],
            'etudiant' => [['title' => 'Bienvenue dans votre espace', 'message' => 'Votre historique personnel apparaîtra dès la mise en service des modules de bibliothèque.', 'level' => 'info']],
            default => [],
        };
    }
}
