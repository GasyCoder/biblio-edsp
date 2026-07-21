<?php
use App\Models\Student;use App\Models\User;use App\Services\VisitService;use Database\Seeders\RolePermissionSeeder;use Inertia\Testing\AssertableInertia as Assert;
beforeEach(fn()=> $this->seed(RolePermissionSeeder::class));
it('shows the overview tab by default for authorized staff', function () {
    $staff = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create();
    app(VisitService::class)->checkIn($student, $staff);

    $this->actingAs($staff)->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Reports/Index')
        ->where('tab', 'overview')
        ->where('overview.metrics.visits', 1)
        ->where('overview.metrics.uniqueStudents', 1)
        ->missing('attendance')
        ->missing('documents')
        ->missing('presence'));
});

it('computes each report tab on demand only', function () {
    $staff = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create(['status' => 'active']);
    app(VisitService::class)->checkIn($student, $staff);

    $this->actingAs($staff)->get(route('reports.index', ['tab' => 'documents']))
        ->assertInertia(fn (Assert $page) => $page->where('tab', 'documents')->has('documents.inventory', 6)->missing('overview'));

    $this->actingAs($staff)->get(route('reports.index', ['tab' => 'presence']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'presence')
            ->has('presence.visits.data', 1)
            ->where('presence.stats.openNow', 1)
            ->where('presence.stats.today', 1)
            ->missing('overview'));
});

it('redirects the legacy attendance url to the attendance tab', function () {
    $staff = User::factory()->create()->assignRole('secretaire');

    $this->actingAs($staff)->get(route('reports.attendance', ['group_by' => 'mention']))
        ->assertRedirect(route('reports.index', ['tab' => 'attendance', 'group_by' => 'mention']));
});
it('prevents students from opening operational reports',function(){ $user=User::factory()->create()->assignRole('etudiant');$this->actingAs($user)->get(route('reports.index'))->assertForbidden();});

it('computes attendance cohort presence and absence by dimension', function () {
    $staff = User::factory()->create()->assignRole('secretaire');
    $level = \App\Models\AcademicLevel::create(['name' => 'Licence 3', 'code' => 'L3', 'sort_order' => 3, 'is_active' => true]);

    $regular = Student::factory()->create(['status' => 'active', 'level_id' => $level->id]);
    $absent = Student::factory()->create(['status' => 'active', 'level_id' => $level->id]);

    // Le régulier vient sur 3 jours distincts (dont 2 passages le même jour = 1 jour de présence).
    foreach (['-3 days', '-2 days', '-1 day', '-1 day'] as $i => $when) {
        \App\Models\Visit::create([
            'visit_number' => "TEST-{$i}",
            'student_id' => $regular->id,
            'checked_in_at' => now()->modify($when),
            'checked_out_at' => now()->modify($when)->addHour(),
            'checked_in_by' => $staff->id,
            'checked_in_role' => 'secretaire',
        ]);
    }

    $this->actingAs($staff)->get(route('reports.index', ['tab' => 'attendance', 'group_by' => 'level']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('tab', 'attendance')
            ->where('attendance.kpis.cohort', 2)
            ->where('attendance.kpis.present', 1)
            ->where('attendance.kpis.absent', 1)
            ->where('attendance.kpis.totalVisits', 4)
            ->where('attendance.kpis.attendanceRate', 50)
            ->where('attendance.breakdown.0.label', 'Licence 3')
            ->where('attendance.breakdown.0.present', 1)
            ->where('attendance.breakdown.0.absent', 1)
            ->where('attendance.breakdown.0.presenceDays', 3)
            ->where('attendance.ranking.0.daysPresent', 3)
            ->where('attendance.ranking.0.score', 6)
            ->has('attendance.absentees', 1)
            ->where('attendance.absentees.0.id', $absent->id));
});

it('prevents students from opening the attendance report', function () {
    $user = User::factory()->create()->assignRole('etudiant');
    $this->actingAs($user)->get(route('reports.index', ['tab' => 'attendance']))->assertForbidden();
});

it('exports the attendance report as a real xlsx workbook', function () {
    $staff = User::factory()->create()->assignRole('secretaire');
    $student = Student::factory()->create(['status' => 'active']);
    app(VisitService::class)->checkIn($student, $staff);

    $response = $this->actingAs($staff)->get(route('reports.attendance.xlsx'));
    $response->assertOk();

    $path = tempnam(sys_get_temp_dir(), 'assiduite-test-').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);

    expect($book->getSheetNames())->toContain('Synthèse', 'Classement', 'Absents')
        ->and($book->getSheetByName('Synthèse')->getCell('A1')->getValue())->toBe('Rapport d’assiduité');
    unlink($path);
});

it('exports the attendance report as a real pdf', function () {
    $staff = User::factory()->create()->assignRole('secretaire');
    Student::factory()->create(['status' => 'active']);

    $response = $this->actingAs($staff)->get(route('reports.attendance.pdf'));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($response->getContent())->toStartWith('%PDF-');
});

it('shows individual attendance stats and cohort rank on the student profile', function () {
    $staff = User::factory()->create()->assignRole('secretaire');
    $level = \App\Models\AcademicLevel::create(['name' => 'Master 1', 'code' => 'M1', 'sort_order' => 4, 'is_active' => true]);

    $star = Student::factory()->create(['status' => 'active', 'level_id' => $level->id]);
    $quiet = Student::factory()->create(['status' => 'active', 'level_id' => $level->id]);

    // 3 jours distincts pour $star (dont un jour à double passage), 1 jour pour $quiet.
    foreach (['-4 days', '-3 days', '-2 days', '-2 days'] as $i => $when) {
        \App\Models\Visit::create([
            'visit_number' => "PROF-S-{$i}", 'student_id' => $star->id,
            'checked_in_at' => now()->modify($when), 'checked_out_at' => now()->modify($when)->addHour(),
            'checked_in_by' => $staff->id, 'checked_in_role' => 'secretaire',
        ]);
    }
    \App\Models\Visit::create([
        'visit_number' => 'PROF-Q-0', 'student_id' => $quiet->id,
        'checked_in_at' => now()->modify('-1 day'), 'checked_out_at' => now()->modify('-1 day')->addHour(),
        'checked_in_by' => $staff->id, 'checked_in_role' => 'secretaire',
    ]);

    $this->actingAs($staff)->get(route('students.show', $star))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Students/Show')
            ->where('attendance.daysPresent', 3)
            ->where('attendance.visits', 4)
            ->where('attendance.score', 6)
            ->where('attendance.rank', 1)
            ->where('attendance.cohortSize', 2)
            ->where('attendance.cohortLabel', 'Master 1')
            ->has('attendance.weeks', 8));

    $this->actingAs($staff)->get(route('students.show', $quiet))
        ->assertInertia(fn (Assert $page) => $page
            ->where('attendance.daysPresent', 1)
            ->where('attendance.rank', 2));
});

it('lists absences per student with partial-attendance nuance', function () {
    $staff = User::factory()->create()->assignRole('secretaire');
    $level = \App\Models\AcademicLevel::create(['name' => 'Licence 1', 'code' => 'L1', 'sort_order' => 1, 'is_active' => true]);

    $partial = Student::factory()->create(['status' => 'active', 'level_id' => $level->id, 'last_name' => 'PARTIEL']);
    $never = Student::factory()->create(['status' => 'active', 'level_id' => $level->id, 'last_name' => 'JAMAIS']);
    $other = Student::factory()->create(['status' => 'active', 'level_id' => $level->id]);

    // 3 jours d'ouverture au total : $partial vient 1 jour, $other vient 3 jours.
    foreach (['-3 days', '-2 days', '-1 day'] as $i => $when) {
        \App\Models\Visit::create([
            'visit_number' => "ABS-O-{$i}", 'student_id' => $other->id,
            'checked_in_at' => now()->modify($when), 'checked_out_at' => now()->modify($when)->addHour(),
            'checked_in_by' => $staff->id, 'checked_in_role' => 'secretaire',
        ]);
    }
    \App\Models\Visit::create([
        'visit_number' => 'ABS-P-0', 'student_id' => $partial->id,
        'checked_in_at' => now()->modify('-2 days'), 'checked_out_at' => now()->modify('-2 days')->addHour(),
        'checked_in_by' => $staff->id, 'checked_in_role' => 'secretaire',
    ]);

    // Tri par jours d'absence décroissants : JAMAIS (3) puis PARTIEL (2) puis other (0).
    $this->actingAs($staff)->get(route('reports.index', ['tab' => 'absences']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'absences')
            ->where('absences.openDays', 3)
            ->where('absences.neverCame', 1)
            ->where('absences.students.total', 3)
            ->where('absences.students.data.0.name', fn ($name) => str_contains($name, 'JAMAIS'))
            ->where('absences.students.data.0.absenceDays', 3)
            ->where('absences.students.data.0.neverCame', true)
            ->where('absences.students.data.1.absenceDays', 2)
            ->where('absences.students.data.1.neverCame', false));

    // Filtre « uniquement les jamais venus ».
    $this->actingAs($staff)->get(route('reports.index', ['tab' => 'absences', 'never_only' => 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('absences.students.total', 1)
            ->where('absences.students.data.0.id', $never->id));

    // Recherche par nom.
    $this->actingAs($staff)->get(route('reports.index', ['tab' => 'absences', 'search' => 'PARTIEL']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('absences.students.total', 1)
            ->where('absences.students.data.0.id', $partial->id));
});

it('exports presences and absences as flat tables', function () {
    $staff = User::factory()->create()->assignRole('secretaire');
    $level = \App\Models\AcademicLevel::create(['name' => 'Licence 2', 'code' => 'L2', 'sort_order' => 2, 'is_active' => true]);
    $student = Student::factory()->create(['status' => 'active', 'level_id' => $level->id, 'last_name' => 'RAKOTO']);
    app(VisitService::class)->checkIn($student, $staff);

    // Présences : impression HTML
    $this->actingAs($staff)->get(route('reports.print', ['tab' => 'presence']))
        ->assertOk()
        ->assertSee('Liste des présences')
        ->assertSee('N° passage')
        ->assertSee('RAKOTO');

    // Présences : xlsx réel avec la bonne feuille
    $response = $this->actingAs($staff)->get(route('reports.export.xlsx', ['tab' => 'presence']));
    $response->assertOk();
    $path = tempnam(sys_get_temp_dir(), 'presences-test-').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    expect($book->getSheetNames())->toContain('Présences')
        ->and($book->getActiveSheet()->getCell('A1')->getValue())->toBe('N° passage');
    unlink($path);

    // Absences : impression HTML avec les colonnes attendues
    $this->actingAs($staff)->get(route('reports.print', ['tab' => 'absences']))
        ->assertOk()
        ->assertSee('Liste des absences')
        ->assertSee('Jours d’absence', false);

    // Absences : PDF réel
    $pdf = $this->actingAs($staff)->get(route('reports.export.pdf', ['tab' => 'absences']));
    $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect($pdf->getContent())->toStartWith('%PDF-');
});
