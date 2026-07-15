<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('creates exactly the three application roles', function () {
    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(['etudiant', 'secretaire', 'superadmin']);
});

it('grants operational permissions to the secretary', function () {
    $user = User::factory()->create();
    $user->assignRole('secretaire');

    expect($user->can('visits.check_in'))->toBeTrue()
        ->and($user->can('roles.manage'))->toBeFalse()
        ->and($user->can('visits.force_close'))->toBeFalse();
});

it('grants every gate ability to the superadmin', function () {
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    expect(Gate::forUser($user)->allows('an-unregistered-ability'))->toBeTrue()
        ->and($user->can('roles.manage'))->toBeTrue()
        ->and($user->getAllPermissions())->not->toBeEmpty();
});

it('does not expose public registration', function () {
    $this->get('/register')->assertNotFound();
});
