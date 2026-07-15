<?php

use App\Enums\NumberType;
use App\Models\Book;
use App\Models\Copy;
use App\Models\Student;
use App\Models\User;
use App\Services\CopyService;
use App\Services\NumberGenerator;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('generates sequential student and copy numbers without using row counts', function () {
    $numbers = app(NumberGenerator::class);

    expect($numbers->next(NumberType::Student))->toBe('EDSP-ETU-'.now()->year.'-000001')
        ->and($numbers->next(NumberType::Student))->toBe('EDSP-ETU-'.now()->year.'-000002')
        ->and($numbers->next(NumberType::Copy))->toBe('EDSP-LIV-000001');
});

it('allows a superadmin to create a student with an automatic number', function () {
    $user = User::factory()->create()->assignRole('superadmin');

    $this->actingAs($user)->post('/students', [
        'academic_number' => 'MAT-001', 'last_name' => 'RAKOTO', 'first_name' => 'Soa', 'status' => 'active',
    ])->assertRedirect(route('students.index'));

    expect(Student::query()->firstOrFail()->registration_number)->toBe('EDSP-ETU-'.now()->year.'-000001');
});

it('allows the secretary to search students but not create them', function () {
    $user = User::factory()->create()->assignRole('secretaire');

    $this->actingAs($user)->get('/students')->assertOk();
    $this->actingAs($user)->post('/students', [])->assertForbidden();
});

it('prevents students from accessing student administration', function () {
    $user = User::factory()->create()->assignRole('etudiant');

    $this->actingAs($user)->get('/students')->assertForbidden();
});

it('allows the secretary to create a book without merging authors or titles', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $payload = ['title' => 'Introduction au droit', 'authors' => ['Jean Auteur'], 'publication_year' => 2020];

    $this->actingAs($user)->post('/books', $payload)->assertRedirect(route('books.index'));
    $this->actingAs($user)->post('/books', $payload)->assertRedirect(route('books.index'));

    expect(Book::query()->count())->toBe(2)
        ->and(Book::query()->with('authors')->get()->pluck('authors')->flatten()->count())->toBe(2);
});

it('allows students to browse the catalogue but not modify it', function () {
    $user = User::factory()->create()->assignRole('etudiant');

    $this->actingAs($user)->get('/books')->assertOk();
    $this->actingAs($user)->post('/books', ['title' => 'Interdit', 'authors' => ['Auteur']])->assertForbidden();
});

it('creates copies with distinct automatic inventory and barcode values', function () {
    $book = Book::factory()->create();
    $service = app(CopyService::class);
    $first = $service->create(['book_id' => $book->id]);
    $second = $service->create(['book_id' => $book->id]);

    expect($first->inventory_number)->toBe('EDSP-LIV-000001')
        ->and($second->inventory_number)->toBe('EDSP-LIV-000002')
        ->and($first->barcode_value)->not->toBe($second->barcode_value)
        ->and(Copy::query()->count())->toBe(2);
});
