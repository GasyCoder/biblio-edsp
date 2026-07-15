<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('requires authentication to view the dashboard', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows administration metrics and actions to the superadmin', function () {
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboard.role', 'superadmin')
            ->has('dashboard.metrics', 4)
            ->where('dashboard.metrics.0.label', 'Comptes utilisateurs')
            ->where('dashboard.quickActions.0.permission', 'users.manage'));
});

it('only exposes operational dashboard actions to the secretary', function () {
    $user = User::factory()->create();
    $user->assignRole('secretaire');

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.role', 'secretaire')
            ->where('dashboard.metrics.0.label', 'Entrées aujourd’hui')
            ->where('dashboard.quickActions', fn ($actions) => $actions->pluck('permission')->contains('visits.check_in')
                && ! $actions->pluck('permission')->contains('users.manage')));
});

it('only exposes personal dashboard data and actions to the student', function () {
    $user = User::factory()->create();
    $user->assignRole('etudiant');

    $this->actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.role', 'etudiant')
            ->where('dashboard.metrics.0.label', 'Présences')
            ->where('dashboard.quickActions', fn ($actions) => $actions->pluck('permission')->sort()->values()->all() === ['catalog.view', 'visits.view_own']));
});
