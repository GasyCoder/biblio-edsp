<?php

use App\Models\Book;
use App\Models\Copy;
use App\Models\Location;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Services\BarcodeService;
use App\Services\CopyService;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('allows the secretary to create and print a physical copy', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $book = Book::factory()->create();

    $this->actingAs($user)->post('/copies', ['book_id' => $book->id, 'condition' => 'good', 'barcode_symbology' => 'code128'])->assertRedirect(route('copies.index'));
    $copy = Copy::query()->firstOrFail();

    expect($copy->inventory_number)->toBe('EDSP-GEN-0001');
    $this->actingAs($user)->get(route('copies.print', $copy))->assertOk()->assertSee('<svg', false)->assertSee($copy->inventory_number);
});

it('prints several selected copies as QR codes', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $book = Book::factory()->create();
    $first = app(CopyService::class)->create(['book_id' => $book->id]);
    $second = app(CopyService::class)->create(['book_id' => $book->id]);

    $this->actingAs($user)->get(route('copies.print.bulk', ['ids' => [$first->id, $second->id]]))
        ->assertOk()
        ->assertSee('Imprimer 2 étiquette(s)')
        ->assertSee($first->inventory_number)
        ->assertSee($second->inventory_number)
        ->assertSee('63,5 × 33,9 mm')
        ->assertSee('grid-template-columns: repeat(3, 63.5mm)', false)
        ->assertSee('<svg', false);
});

it('downloads selected copy labels as a real PDF', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $book = Book::factory()->create();
    $copies = collect([
        app(CopyService::class)->create(['book_id' => $book->id]),
        app(CopyService::class)->create(['book_id' => $book->id]),
    ]);

    $response = $this->actingAs($user)->get(route('copies.print.pdf', ['ids' => $copies->pluck('id')->all()]));

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

it('bulk deletes only copies without operational history', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $book = Book::factory()->create();
    $first = app(CopyService::class)->create(['book_id' => $book->id]);
    $second = app(CopyService::class)->create(['book_id' => $book->id]);

    $this->actingAs($admin)->delete(route('copies.destroy.bulk'), ['ids' => [$first->id, $second->id]])
        ->assertRedirect(route('copies.index'));

    expect(Copy::query()->count())->toBe(0)
        ->and(Copy::withTrashed()->count())->toBe(2);
});

it('creates only one active card per student and prints its QR code', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create();
    $payload = ['student_id' => $student->id, 'type' => 'library', 'symbology' => 'qr'];

    $this->actingAs($user)->post('/cards', $payload)->assertRedirect(route('cards.index'));
    $card = StudentCard::query()->firstOrFail();
    $this->actingAs($user)->post('/cards', $payload)->assertSessionHasErrors('student_id');
    expect($card->type->value)->toBe('library')
        ->and($card->card_number)->toBe($student->registration_number);
    $this->actingAs($user)->get(route('cards.print', $card))->assertOk()
        ->assertSee('<svg', false)
        ->assertSee($student->registration_number)
        ->assertSee('Carte de bibliothèque')
        ->assertSee('85.6mm 53.98mm', false);
});

it('creates library cards for several students in one operation', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $students = Student::factory()->count(3)->create();

    $this->actingAs($user)->post(route('cards.store'), [
        'student_ids' => $students->pluck('id')->all(),
        'expires_at' => now()->addYear()->format('Y-m-d'),
    ])->assertRedirect(route('cards.index'));

    expect(StudentCard::query()->count())->toBe(3)
        ->and(StudentCard::query()->pluck('card_number')->all())
        ->toEqualCanonicalizing($students->pluck('registration_number')->all());
});

it('adapts the library card typography for a long student name', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create([
        'last_name' => 'ANDRIANARISON RAZAFINDRAKOTOARIMANANA',
        'first_name' => 'Jean Chrysostome',
    ]);
    $this->actingAs($user)->post(route('cards.store'), ['student_id' => $student->id]);
    $card = StudentCard::query()->firstOrFail();

    $this->get(route('cards.print', $card))
        ->assertOk()
        ->assertSee('ANDRIANARISON RAZAFINDRAKOTOARIMANANA Jean Chrysostome')
        ->assertSee('name-very-compact', false);
});

it('updates and bulk deletes library cards', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $firstStudent = Student::factory()->create();
    $secondStudent = Student::factory()->create();
    $this->actingAs($admin)->post(route('cards.store'), ['student_id' => $firstStudent->id]);
    $this->post(route('cards.store'), ['student_id' => $secondStudent->id]);
    $cards = StudentCard::query()->orderBy('id')->get();

    $this->patch(route('cards.update', $cards->first()), ['status' => 'suspended', 'expires_at' => now()->addYear()->format('Y-m-d')])
        ->assertRedirect(route('cards.index'));
    expect($cards->first()->refresh()->status->value)->toBe('suspended');

    $this->delete(route('cards.destroy.bulk'), ['ids' => $cards->pluck('id')->all()])
        ->assertRedirect(route('cards.index'));
    expect(StudentCard::query()->count())->toBe(0);
});

it('prints multiple library cards in a new A4 sheet', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $students = Student::factory()->count(2)->create();
    foreach ($students as $student) {
        $this->actingAs($user)->post(route('cards.store'), ['student_id' => $student->id]);
    }
    $cards = StudentCard::query()->get();

    $this->get(route('cards.print.bulk', ['ids' => $cards->pluck('id')->all()]))
        ->assertOk()
        ->assertSee('Imprimer 2 cartes')
        ->assertSee($cards[0]->card_number)
        ->assertSee($cards[1]->card_number)
        ->assertSee('grid-template-columns:repeat(2,85.6mm)', false);
});

it('prevents students from managing copies cards and catalog references', function () {
    $user = User::factory()->create()->assignRole('etudiant');

    $this->actingAs($user)->get('/copies')->assertForbidden();
    $this->actingAs($user)->get('/cards')->assertForbidden();
    $this->actingAs($user)->get('/catalog-references')->assertForbidden();
});

it('generates svg without embedding personal data beyond the opaque value', function () {
    $service = app(BarcodeService::class);

    expect($service->qrSvg('EDSP:CARD:1:TEST'))->toContain('<svg')
        ->and($service->code128Svg('EDSP:COPY:1:TEST'))->toContain('<svg');
});

it('shows and searches copy registration numbers in the books catalog', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $book = Book::factory()->create();
    $this->actingAs($user)->post('/copies', ['book_id' => $book->id, 'condition' => 'good', 'barcode_symbology' => 'code128']);
    $copy = Copy::query()->firstOrFail();

    $this->get(route('books.index', ['search' => $copy->inventory_number]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('books.data', 1)
            ->where('books.data.0.copies.0.inventory_number', $copy->inventory_number));
});

it('filters the physical inventory by search status condition and location', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $location = Location::factory()->create(['name' => 'Étagère recherche', 'code' => 'ETA-R']);
    $matchingBook = Book::factory()->create(['title' => 'Ouvrage inventaire recherché']);
    $matching = app(CopyService::class)->create([
        'book_id' => $matchingBook->id,
        'location_id' => $location->id,
        'status' => 'damaged',
        'condition' => 'fair',
    ]);
    app(CopyService::class)->create(['book_id' => Book::factory()->create()->id]);

    $this->actingAs($user)->get(route('copies.index', [
        'search' => 'inventaire recherché',
        'status' => 'damaged',
        'condition' => 'fair',
        'location' => $location->id,
    ]))->assertInertia(fn (Assert $page) => $page
        ->component('Copies/Index')
        ->has('copies.data', 1)
        ->where('copies.data.0.id', $matching->id)
        ->where('filters.status', 'damaged')
        ->where('filters.condition', 'fair')
        ->where('filters.location', (string) $location->id)
        ->has('locations'));
});

it('updates copy status condition and cabinet location', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $book = Book::factory()->create();
    $copy = app(CopyService::class)->create(['book_id' => $book->id]);

    $this->actingAs($user)->post(route('locations.store'), ['type' => 'cabinet', 'number' => '1'])->assertRedirect();
    $location = Location::query()->firstOrFail();
    expect($location->code)->toBe('ARM-1')->and($location->name)->toBe('Armoire 1');

    $this->patch(route('copies.update', $copy), ['location_id' => $location->id, 'condition' => 'fair', 'status' => 'damaged'])
        ->assertRedirect(route('copies.index'));
    expect($copy->refresh()->status->value)->toBe('damaged')
        ->and($copy->condition->value)->toBe('fair')
        ->and($copy->location_id)->toBe($location->id);
});

it('protects referenced locations and operational copies from deletion', function () {
    $admin = User::factory()->create()->assignRole('superadmin');
    $book = Book::factory()->create();
    $location = Location::factory()->create();
    $copy = app(CopyService::class)->create(['book_id' => $book->id, 'location_id' => $location->id, 'status' => 'in_consultation']);

    $this->actingAs($admin)->delete(route('locations.destroy', $location))->assertSessionHasErrors('location');
    $this->delete(route('copies.destroy', $copy))->assertSessionHasErrors('copy');
    expect(Location::query()->count())->toBe(1)->and(Copy::query()->count())->toBe(1);
});
