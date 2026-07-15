<?php

use App\Models\Book;
use App\Models\Copy;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Services\BarcodeService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('allows the secretary to create and print a physical copy', function () {
    $user = User::factory()->create()->assignRole('secretaire');
    $book = Book::factory()->create();

    $this->actingAs($user)->post('/copies', ['book_id' => $book->id, 'condition' => 'good', 'barcode_symbology' => 'code128'])->assertRedirect(route('copies.index'));
    $copy = Copy::query()->firstOrFail();

    expect($copy->inventory_number)->toBe('EDSP-LIV-000001');
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
