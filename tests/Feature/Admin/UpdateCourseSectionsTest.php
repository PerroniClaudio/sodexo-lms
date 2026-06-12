<?php

use App\Models\Course;
use App\Models\FundingEntity;
use App\Models\Module;

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
