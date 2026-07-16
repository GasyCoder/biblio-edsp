<?php

use App\Models\Student;
use App\Models\User;
use App\Services\VisitService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('exports filtered visits for school control', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create(['last_name' => 'CONTROLE']);
    app(VisitService::class)->checkIn($student, $secretary);

    $this->actingAs($secretary)->get(route('visits.export.xlsx', ['search' => 'CONTROLE']))
        ->assertOk()->assertDownload();
    $this->get(route('visits.export.pdf', ['status' => 'active']))
        ->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('visits.print'))->assertOk()->assertSee('Rapport des présences');
});

it('prevents students from exporting the attendance register', function () {
    $studentUser = User::factory()->create()->assignRole('etudiant');

    $this->actingAs($studentUser)->get(route('visits.export.xlsx'))->assertForbidden();
});
