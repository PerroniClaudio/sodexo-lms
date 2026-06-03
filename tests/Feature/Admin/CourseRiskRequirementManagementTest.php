<?php

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Models\Course;
use App\Models\RiskBasedRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows the risk requirement association section on the course edit page', function () {
    $course = Course::factory()->create([
        'status' => 'draft',
    ]);
    $course->riskBasedRequirements()->attach(
        RiskBasedRequirement::factory()->create(['name' => 'Antincendio'])->getKey(),
        ['course_validity_type' => CourseRiskRequirementValidityType::Both->value],
    );

    $response = $this->get(route('admin.courses.edit', $course));

    $response->assertOk()
        ->assertSeeText('Requisiti di rischio coperti dal corso')
        ->assertSeeText('Antincendio')
        ->assertSeeText('Aggiungi requisito')
        ->assertSeeText('Seleziona requisito di rischio')
        ->assertSeeText('Imposta validità del corso')
        ->assertSeeText('Conferma eliminazione')
        ->assertSeeText('Solo primo conseguimento')
        ->assertSeeText('Solo aggiornamento')
        ->assertSeeText('Solo integrativo')
        ->assertSeeText('Primo conseguimento e aggiornamento');
});

it('updates the risk requirement associations for a course', function () {
    $course = Course::factory()->create([
        'title' => 'Corso multi requisito',
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
    ]);
    $firstRequirement = RiskBasedRequirement::factory()->create(['name' => 'Primo requisito']);
    $secondRequirement = RiskBasedRequirement::factory()->create(['name' => 'Secondo requisito']);

    $response = $this->put(route('admin.courses.update', $course), [
        'title' => 'Corso multi requisito',
        'description' => 'Descrizione aggiornata',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
        'risk_based_requirement_ids' => [
            $firstRequirement->getKey(),
            $secondRequirement->getKey(),
        ],
        'risk_based_requirement_validity_types' => [
            $firstRequirement->getKey() => CourseRiskRequirementValidityType::FirstAchievement->value,
            $secondRequirement->getKey() => CourseRiskRequirementValidityType::Refresh->value,
        ],
    ]);

    $response->assertRedirect(route('admin.courses.edit', $course));

    $course->refresh();
    $associations = $course->riskBasedRequirements()
        ->pluck('course_risk_based_requirement.course_validity_type', 'risk_based_requirements.id')
        ->all();

    expect($associations)->toBe([
        $firstRequirement->getKey() => CourseRiskRequirementValidityType::FirstAchievement->value,
        $secondRequirement->getKey() => CourseRiskRequirementValidityType::Refresh->value,
    ]);
});

it('stores integrative starting risk levels for a course requirement', function () {
    $course = Course::factory()->create([
        'title' => 'Corso integrativo',
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
    ]);
    $requirement = RiskBasedRequirement::factory()
        ->forRiskLevel(RiskLevel::HIGH)
        ->progressionGroup('specific-worker-training')
        ->create(['name' => 'Formazione specifica rischio alto']);

    $response = $this->put(route('admin.courses.update', $course), [
        'title' => 'Corso integrativo',
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
        'risk_based_requirement_ids' => [$requirement->getKey()],
        'risk_based_requirement_validity_types' => [
            $requirement->getKey() => CourseRiskRequirementValidityType::Integrative->value,
        ],
        'risk_based_requirement_integrative_start_levels' => [
            $requirement->getKey() => [
                RiskLevel::LOW->value,
                RiskLevel::MEDIUM->value,
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.courses.edit', $course));

    $pivot = $course->riskBasedRequirements()->firstOrFail()->pivot;

    expect($pivot->course_validity_type)->toBe(CourseRiskRequirementValidityType::Integrative->value)
        ->and(json_decode($pivot->integrative_start_risk_levels, true))
        ->toEqualCanonicalizing([
            RiskLevel::LOW->value,
            RiskLevel::MEDIUM->value,
        ]);
});
