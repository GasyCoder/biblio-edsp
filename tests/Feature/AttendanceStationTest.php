<?php

use App\Models\Book;
use App\Models\Student;
use App\Models\StudentCard;
use App\Models\User;
use App\Models\Visit;
use App\Services\ConsultationService;
use App\Services\CopyService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('uses the same active library card for entry and exit stations', function () {
    $operator = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create(['first_name' => 'Aina', 'last_name' => 'Rasoa']);
    $code = 'EDSP:CARD:'.$student->id.':'.Str::ulid();

    StudentCard::query()->create([
        'student_id' => $student->id,
        'card_number' => $code,
        'type' => 'library',
        'symbology' => 'qr',
        'status' => 'active',
        'issued_at' => now(),
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->post(route('attendance.scan', 'entry'), ['code' => $code])
        ->assertSessionHas('success');

    $visit = Visit::query()->firstOrFail();
    expect($visit->checked_out_at)->toBeNull();

    $this->post(route('attendance.scan', 'exit'), ['code' => $code])
        ->assertSessionHas('success');

    expect($visit->refresh()->checked_out_at)->not->toBeNull();
});

it('does not create duplicates when an entry card is scanned twice', function () {
    $operator = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create(['registration_number' => 'BIB-26-099']);

    $this->actingAs($operator)->post(route('attendance.scan', 'entry'), ['code' => 'BIB-26-099']);
    $this->post(route('attendance.scan', 'entry'), ['code' => 'BIB-26-099'])
        ->assertSessionHas('info');

    expect(Visit::query()->count())->toBe(1);
});

it('blocks station checkout while consultation books are not returned', function () {
    $operator = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create(['registration_number' => 'BIB-26-098']);

    $this->actingAs($operator)->post(route('attendance.scan', 'entry'), ['code' => 'BIB-26-098']);
    $visit = Visit::query()->firstOrFail();
    $session = app(ConsultationService::class)->open($visit, $operator);
    $copy = app(CopyService::class)->create(['book_id' => Book::factory()->create()->id]);
    app(ConsultationService::class)->addCopy($session, $copy, $operator);

    $this->post(route('attendance.scan', 'exit'), ['code' => 'BIB-26-098'])
        ->assertSessionHasErrors('visit');

    expect($visit->refresh()->checked_out_at)->toBeNull();
});

it('renders both dedicated station modes', function () {
    $operator = User::factory()->create()->assignRole('secretaire');

    foreach (['entry', 'exit'] as $mode) {
        $this->actingAs($operator)->get(route('attendance.station', $mode))
            ->assertInertia(fn (Assert $page) => $page->component('Attendance/Station')->where('mode', $mode));
    }
});
