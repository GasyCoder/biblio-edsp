<?php

use App\Models\AcademicLevel;
use App\Models\AcademicMention;
use App\Models\AcademicProgram;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\AcademicReferenceSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed([RolePermissionSeeder::class, AcademicReferenceSeeder::class]);
});

test('the academic reference seeder creates the EDSP structure', function () {
    expect(AcademicLevel::count())->toBe(5)
        ->and(AcademicMention::count())->toBe(2)
        ->and(AcademicProgram::count())->toBe(5)
        ->and(AcademicProgram::where('code', 'DROI')->firstOrFail()->levels()->pluck('code')->sort()->values()->all())->toBe(['L1', 'L2'])
        ->and(AcademicProgram::where('code', 'ETPO')->firstOrFail()->levels()->pluck('code')->sort()->values()->all())->toBe(['M1', 'M2']);
});

test('an administrator can create a student with consistent academic references', function () {
    $user = User::factory()->create();
    $user->assignRole('superadmin');
    $mention = AcademicMention::where('code', 'DROIT')->firstOrFail();
    $program = AcademicProgram::where('code', 'DPRI')->firstOrFail();
    $level = AcademicLevel::where('code', 'L3')->firstOrFail();

    $this->actingAs($user)->post(route('students.store'), [
        'last_name' => 'RAKOTO',
        'first_name' => 'Soa',
        'status' => 'active',
        'mention_id' => $mention->id,
        'program_id' => $program->id,
        'level_id' => $level->id,
    ])->assertRedirect(route('students.index'));

    $student = Student::latest('id')->firstOrFail();
    expect($student->mention_id)->toBe($mention->id)
        ->and($student->program_id)->toBe($program->id)
        ->and($student->level_id)->toBe($level->id)
        ->and($student->program)->toBe('Droit Privé')
        ->and($student->level)->toBe('Licence 3');
});

test('an incompatible level and program are rejected', function () {
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    $this->actingAs($user)->post(route('students.store'), [
        'last_name' => 'RAKOTO',
        'first_name' => 'Soa',
        'status' => 'active',
        'mention_id' => AcademicMention::where('code', 'DROIT')->value('id'),
        'program_id' => AcademicProgram::where('code', 'DROI')->value('id'),
        'level_id' => AcademicLevel::where('code', 'M1')->value('id'),
    ])->assertSessionHasErrors('level_id');

    expect(Student::count())->toBe(0);
});
