<?php

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseEnrollment;
use App\Models\FundingEntity;
use App\Models\JobUnit;
use App\Models\LanguageLevel;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('updates only the course details through the dedicated endpoint', function () {
    $fundingEntity = FundingEntity::factory()->create([
        'company_name' => 'Fondo Impresa',
    ]);

    $course = Course::factory()->create([
        'title' => 'Titolo originale',
        'code' => 'CRS-OLD',
        'description' => 'Descrizione originale',
        'type' => 'res',
        'year' => 2025,
        'status' => 'draft',
        'expiry_date' => '2026-12-31',
        'course_duration_hours' => 8,
    ]);

    $response = $this->put(route('admin.courses.details.update', $course), [
        'title' => 'Titolo aggiornato',
        'code' => 'CRS-NEW',
        'description' => 'Descrizione aggiornata',
        'teaching_material' => 'Dispensa',
        'max_participants' => 25,
        'participant_presence_verification' => 'badge_qr',
        'internal_notes' => 'Note',
        'training_objective' => 'Obiettivo',
        'knowledge' => 'Conoscenze',
        'skills' => 'Abilita',
        'competences' => 'Competenze',
        'regulatory_reference' => 'Normativa',
        'year' => 2026,
        'status' => 'draft',
        'is_financed' => '1',
        'funding_entity_id' => $fundingEntity->getKey(),
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'details']));

    $course->refresh();

    expect($course->title)->toBe('Titolo aggiornato')
        ->and($course->code)->toBe('CRS-NEW')
        ->and($course->description)->toBe('Descrizione aggiornata')
        ->and($course->is_financed)->toBeTrue()
        ->and($course->funding_entity_id)->toBe($fundingEntity->getKey())
        ->and($course->participant_presence_verification)->toBe('badge_qr')
        ->and($course->course_duration_hours)->toBe(8);
});

it('clears funding entity when course is not financed', function () {
    $fundingEntity = FundingEntity::factory()->create();
    $course = Course::factory()->create([
        'is_financed' => true,
        'funding_entity_id' => $fundingEntity->getKey(),
    ]);

    $response = $this->put(route('admin.courses.details.update', $course), [
        'title' => $course->title,
        'code' => $course->code,
        'description' => $course->description,
        'year' => $course->year,
        'status' => $course->status,
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'details']));

    expect($course->fresh()->is_financed)->toBeFalse()
        ->and($course->fresh()->funding_entity_id)->toBeNull();
});

it('requires funding entity when course is financed', function () {
    $course = Course::factory()->create();

    $response = $this->from(route('admin.courses.edit', $course))->put(route('admin.courses.details.update', $course), [
        'title' => $course->title,
        'code' => $course->code,
        'description' => $course->description,
        'year' => $course->year,
        'status' => $course->status,
        'is_financed' => '1',
        'funding_entity_id' => '',
    ]);

    $response->assertRedirect(route('admin.courses.edit', $course))
        ->assertSessionHasErrors('funding_entity_id');
});

it('defaults the required language level for standard courses and forces the lowest level for verification courses', function () {
    $lowestLanguageLevel = LanguageLevel::query()->ordered()->firstOrFail();
    $defaultLanguageLevel = LanguageLevel::factory()->create([
        'name' => 'z-default',
        'sort_order' => $lowestLanguageLevel->sort_order + 10,
        'is_default' => true,
    ]);

    $course = Course::factory()->create([
        'type' => 'res',
        'required_language_level_id' => $lowestLanguageLevel->getKey(),
    ]);

    expect((int) $defaultLanguageLevel->getKey())->not->toBe((int) $lowestLanguageLevel->getKey());

    $standardResponse = $this->put(route('admin.courses.details.update', $course), [
        'title' => $course->title,
        'code' => $course->code,
        'description' => $course->description,
        'year' => $course->year,
        'status' => $course->status,
    ]);

    $standardResponse->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'details']));

    expect((int) $course->fresh()->required_language_level_id)->toBe((int) $defaultLanguageLevel->getKey());

    $verificationResponse = $this->put(route('admin.courses.details.update', $course), [
        'title' => $course->title,
        'code' => $course->code,
        'description' => $course->description,
        'year' => $course->year,
        'status' => $course->status,
        'is_language_verification_course' => '1',
        'grants_language_level_id' => $defaultLanguageLevel->getKey(),
    ]);

    $verificationResponse->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'details']));

    expect($course->fresh()->is_language_verification_course)->toBeTrue()
        ->and((int) $course->fresh()->required_language_level_id)->toBe((int) $lowestLanguageLevel->getKey())
        ->and((int) $course->fresh()->grants_language_level_id)->toBe((int) $defaultLanguageLevel->getKey());
});

it('shows funding fields on course details page', function () {
    $fundingEntity = FundingEntity::factory()->create([
        'company_name' => 'Ente Visualizzato',
    ]);
    $course = Course::factory()->create([
        'is_financed' => true,
        'funding_entity_id' => $fundingEntity->getKey(),
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'details']));

    $response->assertOk()
        ->assertSeeText('Corso finanziato')
        ->assertSeeText('Ente finanziatore')
        ->assertSeeText('Ente Visualizzato');
});

it('shows participant presence verification for res and blended courses only', function (string $type, bool $shouldSeeField) {
    $course = Course::factory()->create([
        'type' => $type,
        'participant_presence_verification' => 'signature',
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'details']));

    $response->assertOk();

    if ($shouldSeeField) {
        $response
            ->assertSeeText('Verifica Presenza Partecipanti')
            ->assertSeeText('Firma presenza')
            ->assertSeeText('Badge/QR')
            ->assertSeeText('Altra modalità');
    } else {
        $response->assertDontSeeText('Verifica Presenza Partecipanti');
    }
})->with([
    'res' => ['res', true],
    'blended' => ['blended', true],
    'fad' => ['fad', false],
]);

it('shows venue section for res and blended courses only', function (string $type, bool $shouldSeeSection) {
    $course = Course::factory()->create([
        'type' => $type,
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'venue']));

    $response->assertOk();

    if ($shouldSeeSection) {
        $response
            ->assertSeeText('Sede')
            ->assertSeeText('Unità produttiva esistente');
    } else {
        $response->assertDontSeeText('Unità produttiva esistente');
    }
})->with([
    'res' => ['res', true],
    'blended' => ['blended', true],
    'fad' => ['fad', false],
]);

it('shows attendees section for classroom and async courses only', function (string $type, bool $shouldSeeSection) {
    $course = Course::factory()->create([
        'type' => $type,
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'attendees']));

    $response->assertOk();

    if ($shouldSeeSection) {
        $response->assertSeeText('Presenti');
    } else {
        $response->assertDontSeeText('Presenti');
    }
})->with([
    'res' => ['res', true],
    'blended' => ['blended', true],
    'async' => ['async', true],
    'fad' => ['fad', false],
]);

it('calculates attendee permanence from consecutive entry and exit records', function () {
    $course = Course::factory()->create([
        'type' => 'res',
    ]);
    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
    ]);
    $module = Module::factory()->create([
        'type' => Module::TYPE_RESIDENTIAL,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);
    $enrollment->moduleProgresses()
        ->where('module_id', $module->getKey())
        ->update(['status' => ModuleProgress::STATUS_COMPLETED]);

    foreach ([
        ['entry', '2026-06-17 09:00:00'],
        ['exit', '2026-06-17 10:15:00'],
        ['entry', '2026-06-17 10:30:00'],
        ['exit', '2026-06-17 11:00:00'],
    ] as [$type, $recordedAt]) {
        DB::table('course_attendance_records')->insert([
            'user_id' => $user->getKey(),
            'course_id' => $course->getKey(),
            'type' => $type,
            'session_id' => (string) Str::uuid(),
            'created_by_user_id' => auth()->id(),
            'recorded_at' => $recordedAt,
        ]);
    }

    $this->get(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->assertOk()
        ->assertSeeText('Rossi Mario')
        ->assertSeeText('mario.rossi@example.test')
        ->assertSeeText('01:45')
        ->assertSeeText('Completato')
        ->assertSeeText('Sì');
});

it('updates course venue with an existing job unit', function () {
    $jobUnit = JobUnit::factory()->create();
    $venue = Venue::factory()->create();
    $course = Course::factory()->res()->create([
        'venue_id' => $venue->getKey(),
        'job_unit_id' => null,
    ]);

    $response = $this->put(route('admin.courses.details.update', $course), [
        'title' => $course->title,
        'code' => $course->code,
        'description' => $course->description,
        'year' => $course->year,
        'status' => $course->status,
        'venue_mode' => 'job_unit',
        'job_unit_id' => $jobUnit->getKey(),
        'update_section' => 'venue',
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'venue']));

    expect($course->fresh()->job_unit_id)->toBe($jobUnit->getKey())
        ->and($course->fresh()->venue_id)->toBeNull();
});

it('creates a reusable venue and assigns it to the course', function () {
    $jobUnit = JobUnit::factory()->create();
    $course = Course::factory()->res()->create();

    $response = $this->put(route('admin.courses.details.update', $course), [
        'title' => $course->title,
        'code' => $course->code,
        'description' => $course->description,
        'year' => $course->year,
        'status' => $course->status,
        'venue_mode' => 'venue',
        'venue_name' => 'Palazzo della regione',
        'country' => $jobUnit->country->code,
        'region' => $jobUnit->region->name,
        'province' => $jobUnit->province->name,
        'city' => $jobUnit->city->name,
        'postal_code' => '00100',
        'address' => 'Via Roma 1',
        'update_section' => 'venue',
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'venue']));

    $venue = Venue::query()->where('name', 'Palazzo della regione')->firstOrFail();

    expect($course->fresh()->venue_id)->toBe($venue->getKey())
        ->and($course->fresh()->job_unit_id)->toBeNull()
        ->and($venue->address)->toBe('Via Roma 1');
});

it('rejects venue fields for unsupported course types', function () {
    $jobUnit = JobUnit::factory()->create();
    $course = Course::factory()->create([
        'type' => 'fad',
    ]);

    $response = $this->from(route('admin.courses.edit', [$course, 'section' => 'details']))
        ->put(route('admin.courses.details.update', $course), [
            'title' => $course->title,
            'code' => $course->code,
            'description' => $course->description,
            'year' => $course->year,
            'status' => $course->status,
            'venue_mode' => 'job_unit',
            'job_unit_id' => $jobUnit->getKey(),
        ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'details']))
        ->assertSessionHasErrors(['venue_mode', 'job_unit_id']);
});

it('rejects participant presence verification for unsupported course types', function () {
    $course = Course::factory()->create([
        'type' => 'fad',
    ]);

    $response = $this->from(route('admin.courses.edit', [$course, 'section' => 'details']))
        ->put(route('admin.courses.details.update', $course), [
            'title' => $course->title,
            'code' => $course->code,
            'description' => $course->description,
            'year' => $course->year,
            'status' => $course->status,
            'participant_presence_verification' => 'signature',
        ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'details']))
        ->assertSessionHasErrors('participant_presence_verification');
});

it('does not change financing fields when published course update is status only', function () {
    $originalFundingEntity = FundingEntity::factory()->create(['company_name' => 'Originale']);
    $newFundingEntity = FundingEntity::factory()->create(['company_name' => 'Nuovo']);
    $course = Course::factory()->published()->create([
        'is_financed' => true,
        'funding_entity_id' => $originalFundingEntity->getKey(),
        'teaching_material' => null,
        'max_participants' => null,
        'internal_notes' => null,
        'training_objective' => null,
        'knowledge' => null,
        'skills' => null,
        'competences' => null,
        'regulatory_reference' => null,
    ]);

    $response = $this->from(route('admin.courses.edit', [$course, 'section' => 'details']))->put(route('admin.courses.details.update', $course), [
        'title' => $course->title,
        'code' => $course->code,
        'description' => $course->description,
        'year' => $course->year,
        'status' => 'archived',
        'is_financed' => '1',
        'funding_entity_id' => $newFundingEntity->getKey(),
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'details']));

    expect($course->fresh()->status)->toBe('archived')
        ->and($course->fresh()->is_financed)->toBeTrue()
        ->and($course->fresh()->funding_entity_id)->toBe($originalFundingEntity->getKey());
});

it('updates only the course duration through the dedicated endpoint', function () {
    $course = Course::factory()->create([
        'title' => 'Titolo invariato',
        'course_start_date' => '2026-01-10',
        'course_end_date' => '2026-01-20',
        'access_closure_date' => '2026-02-10',
        'course_duration_hours' => 6,
        'interaction_duration_minutes' => 120,
        'expiry_date' => '2026-12-31',
    ]);

    $response = $this->put(route('admin.courses.duration.update', $course), [
        'course_start_date' => '2026-03-01',
        'course_end_date' => '2026-03-07',
        'access_closure_date' => '2026-03-31',
        'course_duration_hours' => 10,
        'interaction_duration_minutes' => 180,
        'expiry_date' => '2027-01-31',
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'duration']));

    $course->refresh();

    expect($course->course_start_date?->format('Y-m-d'))->toBe('2026-03-01')
        ->and($course->course_end_date?->format('Y-m-d'))->toBe('2026-03-07')
        ->and($course->access_closure_date?->format('Y-m-d'))->toBe('2026-03-31')
        ->and($course->course_duration_hours)->toBe(10)
        ->and($course->interaction_duration_minutes)->toBe(180)
        ->and($course->title)->toBe('Titolo invariato');
});

it('shows the course program section', function () {
    $course = Course::factory()->create([
        'program_schedule' => [[
            'starts_at' => '09:00',
            'ends_at' => '10:30',
            'duration_hours' => 1,
            'duration_minutes' => 30,
            'teaching_method' => 'lezione_frontale_video_lezione',
            'topic' => 'Sicurezza generale',
        ]],
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'program']));

    $response->assertOk()
        ->assertSeeText('Programma corso')
        ->assertSeeText('Crea nuovo')
        ->assertSeeText('Crea nuovo programma')
        ->assertSeeText('Metodologie Didattiche')
        ->assertSee('Sicurezza generale')
        ->assertSeeText('Lezione frontale / video lezione');
});

it('updates the course program through the dedicated endpoint', function () {
    $course = Course::factory()->create([
        'program_schedule' => null,
    ]);

    $response = $this->put(route('admin.courses.program.update', $course), [
        'program_schedule' => [
            [
                'starts_at' => '09:00',
                'ends_at' => '10:30',
                'duration_hours' => '1',
                'duration_minutes' => '30',
                'teaching_method' => 'lezione_frontale_video_lezione',
                'topic' => 'Introduzione',
            ],
            [
                'starts_at' => '10:45',
                'ends_at' => '12:00',
                'duration_hours' => '1',
                'duration_minutes' => '15',
                'teaching_method' => 'esercitazione',
                'topic' => 'Esercizio guidato',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'program']));

    expect($course->fresh()->program_schedule)->toBe([
        [
            'starts_at' => '09:00',
            'ends_at' => '10:30',
            'duration_hours' => 1,
            'duration_minutes' => 30,
            'teaching_method' => 'lezione_frontale_video_lezione',
            'topic' => 'Introduzione',
        ],
        [
            'starts_at' => '10:45',
            'ends_at' => '12:00',
            'duration_hours' => 1,
            'duration_minutes' => 15,
            'teaching_method' => 'esercitazione',
            'topic' => 'Esercizio guidato',
        ],
    ]);
});

it('drops empty course program rows', function () {
    $course = Course::factory()->create();

    $response = $this->put(route('admin.courses.program.update', $course), [
        'program_schedule' => [
            [
                'starts_at' => '',
                'ends_at' => '',
                'duration_hours' => '',
                'duration_minutes' => '',
                'teaching_method' => '',
                'topic' => '',
            ],
            [
                'starts_at' => '14:00',
                'ends_at' => '',
                'duration_hours' => '',
                'duration_minutes' => '',
                'teaching_method' => '',
                'topic' => '',
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'program']));

    expect($course->fresh()->program_schedule)->toHaveCount(1)
        ->and($course->fresh()->program_schedule[0]['starts_at'])->toBe('14:00');
});

it('validates course program teaching methods', function () {
    $course = Course::factory()->create();

    $response = $this->from(route('admin.courses.edit', [$course, 'section' => 'program']))
        ->put(route('admin.courses.program.update', $course), [
            'program_schedule' => [[
                'starts_at' => '09:00',
                'ends_at' => '10:00',
                'duration_hours' => 1,
                'duration_minutes' => 0,
                'teaching_method' => 'non_valida',
                'topic' => 'Argomento',
            ]],
        ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'program']))
        ->assertSessionHasErrors('program_schedule.0.teaching_method');
});

it('validates course program times', function () {
    $course = Course::factory()->create();

    $response = $this->from(route('admin.courses.edit', [$course, 'section' => 'program']))
        ->put(route('admin.courses.program.update', $course), [
            'program_schedule' => [[
                'starts_at' => '9',
                'ends_at' => '10:00',
                'duration_hours' => 1,
                'duration_minutes' => 0,
                'teaching_method' => 'esercitazione',
                'topic' => 'Argomento',
            ]],
        ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'program']))
        ->assertSessionHasErrors('program_schedule.0.starts_at');
});

it('updates survey settings through the dedicated endpoint and syncs the survey module', function () {
    $course = Course::factory()->create([
        'status' => 'draft',
        'has_satisfaction_survey' => false,
        'satisfaction_survey_required_for_certificate' => false,
    ]);

    $response = $this->put(route('admin.courses.survey.update', $course), [
        'has_satisfaction_survey' => '1',
        'satisfaction_survey_required_for_certificate' => '1',
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'survey']));

    $course->refresh();

    expect($course->has_satisfaction_survey)->toBeTrue()
        ->and($course->satisfaction_survey_required_for_certificate)->toBeTrue()
        ->and($course->satisfactionModules()->count())->toBe(1)
        ->and($course->satisfactionModules()->first()?->type)->toBe(Module::TYPE_SATISFACTION_QUIZ);
});

it('updates course categorization event type and categories', function () {
    $course = Course::factory()->create([
        'event_type' => null,
    ]);
    $category = CourseCategory::factory()->create();

    $response = $this->put(route('admin.courses.categories.update', $course), [
        'event_type' => 'formazione obbligatoria',
        'category_ids' => [$category->getKey()],
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'categorization']));

    $course->refresh();

    expect($course->event_type)->toBe('formazione obbligatoria')
        ->and($course->categories)->toHaveCount(1)
        ->and($course->categories->first()?->is($category))->toBeTrue();
});

it('shows event type field in course categorization section', function () {
    $course = Course::factory()->create([
        'event_type' => 'addestramento',
    ]);

    $response = $this->get(route('admin.courses.edit', [$course, 'section' => 'categorization']));

    $response->assertOk()
        ->assertSeeText('Tipologia evento')
        ->assertSee('addestramento');
});

it('validates course categorization event type', function () {
    $course = Course::factory()->create();

    $response = $this->from(route('admin.courses.edit', [$course, 'section' => 'categorization']))
        ->put(route('admin.courses.categories.update', $course), [
            'event_type' => 'non valido',
        ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'categorization']))
        ->assertSessionHasErrors('event_type');
});
