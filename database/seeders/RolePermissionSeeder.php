<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'superadmin' => [],
        'secretaire' => [
            'dashboard.operational',
            'students.view', 'students.update',
            'cards.view', 'cards.create', 'cards.update', 'cards.replace', 'cards.suspend', 'cards.print', 'cards.scan',
            'books.view', 'books.create', 'books.update',
            'authors.view', 'authors.create', 'authors.update',
            'categories.view', 'categories.create', 'categories.update',
            'copies.view', 'copies.create', 'copies.update', 'copies.print',
            'locations.view', 'locations.create', 'locations.update',
            'visits.view', 'visits.check_in', 'visits.check_out',
            'consultations.view', 'consultations.open', 'consultations.add_copy', 'consultations.return_copy', 'consultations.close',
            'loans.view', 'loans.create', 'loans.return', 'loans.close',
            'imports.view', 'imports.upload', 'imports.review',
            'reports.operational',
        ],
        'etudiant' => [
            'dashboard.personal',
            'catalog.view',
            'profile.view_own',
            'visits.view_own',
            'consultations.view_own',
            'loans.view_own',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::ROLE_PERMISSIONS)
            ->flatten()
            ->merge([
                'users.manage', 'roles.manage', 'permissions.manage',
                'students.manage', 'cards.manage', 'catalog.manage',
                'visits.force_close', 'consultations.force_close', 'loans.force_close',
                'imports.validate', 'imports.commit', 'imports.cancel',
                'reports.statistics', 'reports.export',
                'settings.manage', 'audit.view',
            ])
            ->unique()
            ->sort()
            ->values();

        $permissions->each(
            fn (string $permission) => Permission::findOrCreate($permission, 'web'),
        );

        // DatabaseSeeder utilise WithoutModelEvents : l'invalidation automatique du
        // cache de permissions (sur l'event `saved`) ne se déclenche pas ici, donc le
        // cache en mémoire resterait périmé (vide) pour syncPermissions() ci-dessous.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $rolePermissions) {
            Role::findOrCreate($roleName, 'web')->syncPermissions(
                $roleName === 'superadmin' ? $permissions : $rolePermissions,
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
