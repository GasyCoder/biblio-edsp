<?php

use App\Enums\NumberType;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Copy;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Services\CopyService;
use App\Services\NumberGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('separates catalogue references into dedicated pages', function () {
    $user = User::factory()->create()->assignRole('secretaire');

    $this->actingAs($user)->get(route('catalog-references.index'))
        ->assertInertia(fn (Assert $page) => $page->component('CatalogReferences/Index')->has('counts'));
    $this->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page->component('CatalogReferences/Categories')->has('categories'));
    $this->get(route('authors.index'))
        ->assertInertia(fn (Assert $page) => $page->component('CatalogReferences/Authors')->has('authors.data'));
    $this->get(route('locations.index'))
        ->assertInertia(fn (Assert $page) => $page->component('CatalogReferences/Locations')->has('locations'));
});

it('searches independently in every catalogue reference', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    Category::create(['name' => 'Droit commercial', 'slug' => 'droit-commercial', 'inventory_code' => 'DRC']);
    Category::create(['name' => 'Science politique', 'slug' => 'science-politique', 'inventory_code' => 'SP']);
    Author::create(['display_name' => 'Jean Touchard']);
    Author::create(['display_name' => 'Auteur différent']);
    \App\Models\Location::create(['type' => 'cabinet', 'number' => '1', 'name' => 'Armoire 1', 'code' => 'ARM-1']);
    \App\Models\Location::create(['type' => 'shelf', 'number' => '2', 'name' => 'Étagère 2', 'code' => 'ETA-2']);

    $this->actingAs($user)->get(route('categories.index', ['search' => 'DRC']))
        ->assertInertia(fn (Assert $page) => $page->has('categories', 1)->where('filters.search', 'DRC'));
    $this->get(route('authors.index', ['search' => 'Touchard']))
        ->assertInertia(fn (Assert $page) => $page->has('authors.data', 1)->where('filters.search', 'Touchard'));
    $this->get(route('locations.index', ['search' => 'ARM-1']))
        ->assertInertia(fn (Assert $page) => $page->has('locations', 1)->where('filters.search', 'ARM-1'));
});

it('generates sequential student and copy numbers without using row counts', function () {
    $numbers = app(NumberGenerator::class);

    expect($numbers->next(NumberType::Student))->toBe('BIB-'.now()->format('y').'-001')
        ->and($numbers->next(NumberType::Student))->toBe('BIB-'.now()->format('y').'-002')
        ->and($numbers->next(NumberType::Copy))->toBe('EDSP-GEN-0001');
});

it('allows a superadmin to create a student with an automatic number', function () {
    $user = User::factory()->create()->assignRole('superadmin');

    $this->actingAs($user)->post('/students', [
        'academic_number' => 'MAT-001', 'last_name' => 'RAKOTO', 'first_name' => 'Soa', 'status' => 'active',
    ])->assertRedirect(route('students.index'));

    expect(Student::query()->firstOrFail()->registration_number)->toBe('BIB-'.now()->format('y').'-001');
});

it('stores a scanned identity photo for the library card', function () {
    Storage::fake('public');
    $user = User::factory()->create()->assignRole('superadmin');

    $this->actingAs($user)->post(route('students.store'), [
        'last_name' => 'PHOTO', 'first_name' => 'Test', 'status' => 'active',
        'photo' => UploadedFile::fake()->image('identite.jpg', 400, 500),
    ])->assertRedirect(route('students.index'));

    $student = Student::query()->firstOrFail();
    Storage::disk('public')->assertExists($student->photo_path);
    $this->get(route('students.show', $student))->assertInertia(fn (Assert $page) => $page
        ->component('Students/Show')
        ->where('student.registration_number', $student->registration_number)
        ->where('student.photo_url', Storage::disk('public')->url($student->photo_path))
        ->has('student.cards')
        ->has('student.visits'));
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

it('stores a cover image and displays the detailed book page', function () {
    Storage::fake('public');
    $user = User::factory()->create()->assignRole('secretaire');

    $this->actingAs($user)->post(route('books.store'), [
        'title' => 'Ouvrage avec couverture',
        'authors' => ['Auteur Test'],
        'cover' => UploadedFile::fake()->image('couverture.jpg', 600, 900),
    ])->assertRedirect(route('books.index'));

    $book = Book::query()->firstOrFail();
    Storage::disk('public')->assertExists($book->cover_path);
    $this->get(route('books.show', $book))->assertInertia(fn (Assert $page) => $page
        ->component('Books/Show')
        ->where('book.title', 'Ouvrage avec couverture')
        ->where('book.cover_url', Storage::disk('public')->url($book->cover_path)));
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

it('bulk deletes students only when none has library history', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $first = Student::factory()->create();
    $second = Student::factory()->create();

    $this->actingAs($admin)->delete(route('students.destroy.bulk'), ['ids' => [$first->id, $second->id]])
        ->assertRedirect(route('students.index'));

    expect(Student::query()->count())->toBe(0)
        ->and(Student::withTrashed()->count())->toBe(2);
});

it('cancels bulk student deletion when one selected student has a card', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $protected = Student::factory()->create();
    $deletable = Student::factory()->create();
    StudentCard::create([
        'student_id' => $protected->id,
        'card_number' => 'CARD-BULK-TEST',
        'type' => 'library',
        'symbology' => 'qr',
        'status' => 'active',
        'issued_at' => now(),
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->delete(route('students.destroy.bulk'), ['ids' => [$protected->id, $deletable->id]])
        ->assertSessionHasErrors('student');

    expect(Student::query()->count())->toBe(2);
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

it('bulk deletes unused categories and authors', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $categories = collect([
        Category::create(['name' => 'Catégorie A', 'slug' => 'categorie-a', 'inventory_code' => 'CA']),
        Category::create(['name' => 'Catégorie B', 'slug' => 'categorie-b', 'inventory_code' => 'CB']),
    ]);
    $authors = collect([
        Author::create(['display_name' => 'Auteur A']),
        Author::create(['display_name' => 'Auteur B']),
    ]);

    $this->actingAs($admin)->delete(route('categories.destroy.bulk'), ['ids' => $categories->pluck('id')->all()])
        ->assertSessionHasNoErrors();
    $this->delete(route('authors.destroy.bulk'), ['ids' => $authors->pluck('id')->all()])
        ->assertSessionHasNoErrors();

    expect(Category::query()->count())->toBe(0)
        ->and(Author::query()->count())->toBe(0);
});

it('cancels bulk reference deletion when a category or author is used', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $category = Category::create(['name' => 'Catégorie utilisée', 'slug' => 'categorie-utilisee', 'inventory_code' => 'CU']);
    $otherCategory = Category::create(['name' => 'Catégorie libre', 'slug' => 'categorie-libre', 'inventory_code' => 'CL']);
    $author = Author::create(['display_name' => 'Auteur utilisé']);
    $otherAuthor = Author::create(['display_name' => 'Auteur libre']);
    $book = Book::factory()->create(['category_id' => $category->id]);
    $book->authors()->attach($author->id, ['position' => 1]);

    $this->actingAs($admin)->delete(route('categories.destroy.bulk'), ['ids' => [$category->id, $otherCategory->id]])
        ->assertSessionHasErrors('category');
    $this->delete(route('authors.destroy.bulk'), ['ids' => [$author->id, $otherAuthor->id]])
        ->assertSessionHasErrors('author');

    expect(Category::query()->count())->toBe(2)
        ->and(Author::query()->count())->toBe(2);
});
