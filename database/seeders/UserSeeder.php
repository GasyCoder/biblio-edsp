<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, array{name: string, email: string, password: ?string}> $accounts */
        $accounts = config('initial-users');

        foreach ($accounts as $role => $account) {
            if (blank($account['password'])) {
                throw new InvalidArgumentException("Le mot de passe initial du rôle {$role} n'est pas configuré.");
            }

            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'email_verified_at' => now(),
                    'password' => Hash::make($account['password']),
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
