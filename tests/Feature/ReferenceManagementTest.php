<?php

use App\Enums\NumberType;
use App\Models\Book;
use App\Models\Category;
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

    expect($numbers->next(NumberType::Student))->toBe('ETU-'.now()->format('y').'-001')
        ->and($numbers->next(NumberType::Student))->toBe('ETU-'.now()->format('y').'-002')
        ->and($numbers->next(NumberType::Copy))->toBe('EDSP-GEN-0001');
});

it('allows a superadmin to create a student with an automatic number', function () {
    $user = User::factory()->create()->assignRole('superadmin');

    $this->actingAs($user)->post('/students', [
        'academic_number' => 'MAT-001', 'last_name' => 'RAKOTO', 'first_name' => 'Soa', 'status' => 'active',
    ])->assertRedirect(route('students.index'));

    expect(Student::query()->firstOrFail()->registration_number)->toBe('ETU-'.now()->format('y').'-001');
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

    expect($first->inventory_number)->toBe('EDSP-GEN-0001')
        ->and($second->inventory_number)->toBe('EDSP-GEN-0002')
        ->and($first->barcode_value)->not->toBe($second->barcode_value)
        ->and(Copy::query()->count())->toBe(2);
});

it('uses the book category code in the global copy sequence', function () {
    $relations = Category::create(['name' => 'Relations internationales', 'slug' => 'relations-internationales', 'inventory_code' => 'RI']);
    $commercial = Category::create(['name' => 'Droit commercial', 'slug' => 'droit-commercial', 'inventory_code' => 'DRC']);
    $first = app(CopyService::class)->create(['book_id' => Book::factory()->create(['category_id' => $relations->id])->id]);
    $second = app(CopyService::class)->create(['book_id' => Book::factory()->create(['category_id' => $commercial->id])->id]);

    expect($first->inventory_number)->toBe('EDSP-RI-0001')
        ->and($second->inventory_number)->toBe('EDSP-DRC-0002');
});

it('updates and safely deletes students without history', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $student = Student::factory()->create();
    $this->actingAs($admin)->patch(route('students.update', $student), ['last_name' => 'NOUVEAU', 'first_name' => 'Nom', 'status' => 'active'])->assertRedirect(route('students.index'));
    expect($student->refresh()->last_name)->toBe('NOUVEAU');
    $this->delete(route('students.destroy', $student))->assertRedirect(route('students.index'));
    expect(Student::query()->count())->toBe(0);
});

it('updates books and prevents deletion while copies exist', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $book = Book::factory()->create();
    $this->actingAs($admin)->patch(route('books.update', $book), ['title' => 'Titre corrigé', 'authors' => ['Auteur corrigé']])->assertRedirect(route('books.index'));
    expect($book->refresh()->title)->toBe('Titre corrigé');
    app(CopyService::class)->create(['book_id' => $book->id]);
    $this->delete(route('books.destroy', $book))->assertSessionHasErrors('book');
    expect(Book::query()->count())->toBe(1);
});

it('bulk deletes books only when none has copies', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $first = Book::factory()->create();
    $second = Book::factory()->create();

    $this->actingAs($admin)->delete(route('books.destroy.bulk'), ['ids' => [$first->id, $second->id]])
        ->assertRedirect(route('books.index'));

    expect(Book::query()->count())->toBe(0)
        ->and(Book::withTrashed()->count())->toBe(2);
});

it('cancels bulk book deletion when one selected book has copies', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $protected = Book::factory()->create();
    $deletable = Book::factory()->create();
    app(CopyService::class)->create(['book_id' => $protected->id]);

    $this->actingAs($admin)->delete(route('books.destroy.bulk'), ['ids' => [$protected->id, $deletable->id]])
        ->assertSessionHasErrors('book');

    expect(Book::query()->count())->toBe(2);
});
