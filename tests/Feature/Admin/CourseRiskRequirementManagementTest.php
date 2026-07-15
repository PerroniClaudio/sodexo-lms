<?php

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Models\Course;
use App\Models\RiskBasedRequirement;

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
        ['course_validity_types' => json_encode([
            CourseRiskRequirementValidityType::FirstAchievement->value,
            CourseRiskRequirementValidityType::Refresh->value,
        ])],
    );

    $response = $this->get(route('admin.courses.edit', $course));

    $response->assertOk()
        ->assertSeeText('Abilitazioni di rischio acquisite')
        ->assertSeeText('Antincendio')
        ->assertSeeText('Aggiungi requisito')
        ->assertSeeText('Seleziona requisito di rischio')
        ->assertSeeText('Imposta validità del corso')
        ->assertSeeText('Conferma eliminazione')
        ->assertSeeText('Primo conseguimento')
        ->assertSeeText('Aggiornamento')
        ->assertSeeText('Integrativo');
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

    $response = $this->put(route('admin.courses.certificates.update', $course), [
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
            $firstRequirement->getKey() => [CourseRiskRequirementValidityType::FirstAchievement->value],
            $secondRequirement->getKey() => [CourseRiskRequirementValidityType::Refresh->value],
        ],
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'certificates']));

    $course->refresh();
    $associations = $course->riskBasedRequirements()
        ->get()
        ->mapWithKeys(fn (RiskBasedRequirement $requirement): array => [
            $requirement->getKey() => $requirement->pivot->course_validity_types,
        ])
        ->all();

    expect($associations)->toBe([
        $firstRequirement->getKey() => [CourseRiskRequirementValidityType::FirstAchievement->value],
        $secondRequirement->getKey() => [CourseRiskRequirementValidityType::Refresh->value],
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

    $response = $this->put(route('admin.courses.certificates.update', $course), [
        'title' => 'Corso integrativo',
        'description' => 'Descrizione',
        'year' => 2026,
        'expiry_date' => '2026-12-31',
        'status' => 'draft',
        'risk_based_requirement_ids' => [$requirement->getKey()],
        'risk_based_requirement_validity_types' => [
            $requirement->getKey() => [
                CourseRiskRequirementValidityType::Refresh->value,
                CourseRiskRequirementValidityType::Integrative->value,
            ],
        ],
        'risk_based_requirement_integrative_start_levels' => [
            $requirement->getKey() => [
                RiskLevel::LOW->value,
                RiskLevel::MEDIUM->value,
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'certificates']));

    $pivot = $course->riskBasedRequirements()->firstOrFail()->pivot;

    expect($pivot->course_validity_types)->toEqualCanonicalizing([
        CourseRiskRequirementValidityType::Refresh->value,
        CourseRiskRequirementValidityType::Integrative->value,
    ])
        ->and($pivot->integrative_start_risk_levels)
        ->toEqualCanonicalizing([
            RiskLevel::LOW->value,
            RiskLevel::MEDIUM->value,
        ]);
});
