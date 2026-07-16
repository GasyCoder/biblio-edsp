<?php

use App\Models\Student;
use App\Models\User;
use App\Services\VisitService;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lists and filters visits for library staff', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create(['last_name' => 'RAKOTO']);
    app(VisitService::class)->checkIn($student, $secretary);

    $this->actingAs($secretary)->get(route('visits.index', ['status' => 'active', 'search' => 'RAKOTO']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Visits/Index')
            ->has('studentGroups.data', 1)
            ->where('stats.active', 1)
            ->where('ownOnly', false));
});

it('shows only the authenticated students own visits', function () {
    $user = User::factory()->create()->assignRole('etudiant');
    $operator = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create(['user_id' => $user->id]);
    $other = Student::factory()->create();
    app(VisitService::class)->checkIn($student, $operator);
    app(VisitService::class)->checkIn($other, $operator);

    $this->actingAs($user)->get(route('visits.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('studentGroups.data', 1)
            ->where('studentGroups.data.0.id', $student->id)
            ->where('ownOnly', true));
});
