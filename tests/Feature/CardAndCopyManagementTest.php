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

it('creates only one active card per student and prints its QR code', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create();
    $payload = ['student_id' => $student->id, 'type' => 'library', 'symbology' => 'qr'];

    $this->actingAs($user)->post('/cards', $payload)->assertRedirect(route('cards.index'));
    $card = StudentCard::query()->firstOrFail();
    $this->actingAs($user)->post('/cards', $payload)->assertSessionHasErrors('student_id');
    $this->actingAs($user)->get(route('cards.print', $card))->assertOk()->assertSee('<svg', false)->assertSee($student->registration_number);
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
