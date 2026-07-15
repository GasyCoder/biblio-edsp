<?php

use App\Models\ImportBatch;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

function studentSpreadsheet(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['matricule', 'nom', 'prenom', 'sexe', 'code_redoublement', 'date_naissance', 'nationalite', 'telephone', 'adresse'],
        ...$rows,
    ]);
    $path = tempnam(sys_get_temp_dir(), 'students').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'etudiants.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('previews and commits valid student spreadsheet rows with automatic numbers', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    $superadmin = User::factory()->create()->assignRole('superadmin');
    $file = studentSpreadsheet([
        ['3254.D', 'ABDOULWAHABI', 'Ben Abdouroihamane', 'M', 'N', '2006-03-21', 'Malagasy', '038 33 573 15', 'ANTANIMASAJA'],
        ['3255.D', 'ANDRIAMANALINARIVO', 'Warda', 'F', 'R', '2008-12-10', 'Malagasy', '032 15 632 24', 'MAJUNGA BE'],
    ]);

    $this->actingAs($secretary)->post(route('student-imports.store'), ['file' => $file])->assertRedirect();
    $import = ImportBatch::query()->firstOrFail();
    expect($import->valid_rows)->toBe(2)->and($import->error_rows)->toBe(0);

    $this->actingAs($secretary)->post(route('student-imports.commit', $import))->assertForbidden();
    $this->actingAs($superadmin)->post(route('student-imports.commit', $import))->assertRedirect();

    expect(Student::query()->count())->toBe(2)
        ->and(Student::query()->where('academic_number', '3254.D')->firstOrFail()->registration_number)->toStartWith('ETU-'.now()->format('y').'-')
        ->and(Student::query()->where('academic_number', '3255.D')->firstOrFail()->repetition_code)->toBe('R');
});

it('keeps invalid and duplicate rows for human review', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    Student::factory()->create(['academic_number' => '3254.D']);
    $file = studentSpreadsheet([
        ['3254.D', 'DUPONT', 'Jean', 'M', 'N', '2000-01-01', null, null, null],
        ['3256.D', null, 'Sans nom', 'X', 'N', '2030-01-01', null, null, null],
    ]);

    $this->actingAs($secretary)->post(route('student-imports.store'), ['file' => $file]);
    $import = ImportBatch::query()->firstOrFail();

    expect($import->error_rows)->toBe(2)
        ->and($import->rows()->where('status', 'error')->count())->toBe(2);
});

it('exports students as an xlsx file', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    Student::factory()->create();

    $this->actingAs($secretary)->get(route('student-exports.xlsx'))
        ->assertOk()
        ->assertDownload();
});
