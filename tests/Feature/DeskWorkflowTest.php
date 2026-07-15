<?php

use App\Enums\CopyStatus;
use App\Models\Book;
use App\Models\ConsultationItem;
use App\Models\ConsultationSession;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Models\Visit;
use App\Services\CopyService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('runs the complete desk workflow from card scan to checkout', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create();
    $card = StudentCard::query()->create([
        'student_id' => $student->id,
        'card_number' => 'EDSP:CARD:1:'.Str::ulid(),
        'type' => 'library',
        'symbology' => 'qr',
        'status' => 'active',
        'issued_at' => now(),
        'created_by' => $secretary->id,
    ]);
    $copy = app(CopyService::class)->create(['book_id' => Book::factory()->create()->id]);

    $this->actingAs($secretary)->get(route('desk.index', ['q' => $card->card_number]))->assertOk();
    $this->post(route('desk.check-in', $student))->assertRedirect();

    $visit = Visit::query()->firstOrFail();
    expect($visit->visit_number)->toStartWith('EDSP-PTG-'.now()->format('Ymd'));

    $this->post(route('desk.consultations.open', $visit))->assertRedirect();
    $session = ConsultationSession::query()->firstOrFail();
    expect($session->session_number)->toStartWith('EDSP-CST-'.now()->format('Ymd'));

    $this->post(route('desk.consultations.copies.store', $session), ['barcode' => $copy->barcode_value])->assertRedirect();
    expect($copy->refresh()->status)->toBe(CopyStatus::InConsultation);

    $this->post(route('desk.check-out', $visit))->assertSessionHasErrors('visit');
    $this->post(route('desk.consultations.close', $session))->assertSessionHasErrors('session');

    $item = ConsultationItem::query()->firstOrFail();
    $this->post(route('desk.consultations.copies.return', $item))->assertRedirect();
    expect($copy->refresh()->status)->toBe(CopyStatus::Available);

    $this->post(route('desk.consultations.close', $session))->assertRedirect();
    $this->post(route('desk.check-out', $visit))->assertRedirect(route('desk.index'));

    expect($visit->refresh()->checked_out_at)->not->toBeNull()
        ->and($session->refresh()->closed_at)->not->toBeNull();
});

it('prevents duplicate open visits and duplicate copy scans', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create();
    $copy = app(CopyService::class)->create(['book_id' => Book::factory()->create()->id]);

    $this->actingAs($secretary)->post(route('desk.check-in', $student));
    $this->post(route('desk.check-in', $student))->assertSessionHasErrors('student');
    $visit = Visit::query()->firstOrFail();
    $this->post(route('desk.consultations.open', $visit));
    $session = ConsultationSession::query()->firstOrFail();
    $this->post(route('desk.consultations.copies.store', $session), ['barcode' => $copy->barcode_value]);
    $this->post(route('desk.consultations.copies.store', $session), ['barcode' => $copy->barcode_value])->assertSessionHasErrors('copy');

    expect(Visit::query()->count())->toBe(1)
        ->and(ConsultationItem::query()->count())->toBe(1);
});

it('forbids students from using desk operations', function () {
    $studentUser = User::factory()->create()->assignRole('etudiant');

    $this->actingAs($studentUser)->get(route('desk.index'))->assertForbidden();
});

it('returns candidate choices instead of selecting an ambiguous name', function () {
    $secretary = User::factory()->create()->assignRole('secretaire');
    Student::factory()->create(['last_name' => 'Rakoto']);
    Student::factory()->create(['last_name' => 'Rakotovao']);

    $this->actingAs($secretary)->get(route('desk.index', ['q' => 'Rakoto']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('student', null)
            ->has('matches', 2));
});
