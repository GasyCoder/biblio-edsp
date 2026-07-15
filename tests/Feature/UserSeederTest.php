<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

it('creates one initial user for each application role', function () {
    config()->set('initial-users', [
        'superadmin' => ['name' => 'Admin Test', 'email' => 'admin@test.local', 'password' => 'Admin-Test-123!'],
        'secretaire' => ['name' => 'Secrétaire Test', 'email' => 'secretary@test.local', 'password' => 'Secretary-Test-123!'],
        'etudiant' => ['name' => 'Étudiant Test', 'email' => 'student@test.local', 'password' => 'Student-Test-123!'],
    ]);

    $this->seed([RolePermissionSeeder::class, UserSeeder::class]);

    foreach (config('initial-users') as $role => $account) {
        $user = User::query()->where('email', $account['email'])->firstOrFail();

        expect($user->hasExactRoles($role))->toBeTrue()
            ->and($user->email_verified_at)->not->toBeNull()
            ->and(Hash::check($account['password'], $user->password))->toBeTrue();
    }
});

it('is idempotent and does not duplicate initial users', function () {
    config()->set('initial-users', [
        'superadmin' => ['name' => 'Admin Test', 'email' => 'admin@test.local', 'password' => 'Admin-Test-123!'],
        'secretaire' => ['name' => 'Secrétaire Test', 'email' => 'secretary@test.local', 'password' => 'Secretary-Test-123!'],
        'etudiant' => ['name' => 'Étudiant Test', 'email' => 'student@test.local', 'password' => 'Student-Test-123!'],
    ]);

    $this->seed(RolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(3);
});
