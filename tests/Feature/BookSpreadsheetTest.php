<?php

use App\Models\Book;
use App\Models\Copy;
use App\Models\ImportBatch;
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

function bookSpreadsheet(): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([
        ['INVENTAIRE DES OUVRAGES'], [],
        ['Numero', 'Categorie', "Titre de l'Ouvrage", "Année et Maison d'Edition", 'Auteur', "Nombre d'ouvrage"],
        [1, 'Science Politique', 'Histoire des idées politiques Tome 1', 'Thémis 1991', 'Jean TOUCHARD', 3],
        [2, null, 'Ouvrage avec auteurs sur plusieurs lignes', 'Dalloz 2020', 'Auteur UN', null],
        [null, null, null, null, 'Auteur DEUX', 2],
        [3, 'Droit Civil', 'Quantité absente', 'Dalloz 2019', 'Auteur TROIS', null],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'books').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'inventaire.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('previews books with carried categories continuation authors and quantities', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    $this->actingAs($secretary)->post(route('book-imports.store'), ['file' => bookSpreadsheet()])->assertRedirect();
    $import = ImportBatch::query()->firstOrFail();

    expect($import->total_rows)->toBe(3)->and($import->valid_rows)->toBe(2)->and($import->error_rows)->toBe(1);
    $second = $import->rows()->where('row_number', 5)->firstOrFail();
    expect($second->normalized_data['category'])->toBe('Science Politique')
        ->and($second->normalized_data['authors'])->toBe(['Auteur UN', 'Auteur DEUX'])
        ->and($second->normalized_data['quantity'])->toBe(2);
});

it('analyzes several uploaded book spreadsheets in one request', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');

    $this->actingAs($secretary)
        ->post(route('book-imports.store'), ['files' => [bookSpreadsheet(), bookSpreadsheet()]])
        ->assertRedirect(route('book-imports.index'));

    expect(ImportBatch::query()->where('type', 'books')->count())->toBe(2);
});

it('creates one physical copy per imported quantity without merging titles', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    $superadmin = User::factory()->create()->assignRole('superadmin');
    $this->actingAs($secretary)->post(route('book-imports.store'), ['file' => bookSpreadsheet()]);
    $import = ImportBatch::query()->firstOrFail();
    $this->actingAs($superadmin)->post(route('book-imports.commit', $import))->assertRedirect();

    expect(Book::query()->count())->toBe(2)
        ->and(Copy::query()->count())->toBe(5)
        ->and(Copy::query()->distinct()->count('inventory_number'))->toBe(5);
});

it('exports the catalog as an Excel workbook', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    $this->actingAs($secretary)->get(route('book-exports.xlsx'))->assertOk()->assertDownload();
});
