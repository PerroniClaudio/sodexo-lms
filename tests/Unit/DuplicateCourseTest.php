<?php

use App\Actions\DuplicateCourse;
use App\Actions\DuplicateCourseStructure;
use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Models\Course;
use App\Models\RiskBasedRequirement;

it('copies risk requirement JSON attributes when duplicating a course', function () {
    $sourceCourse = Course::factory()->create();
    $requirement = RiskBasedRequirement::factory()->create();
    $validityTypes = [
        CourseRiskRequirementValidityType::Refresh->value,
        CourseRiskRequirementValidityType::Integrative->value,
    ];
    $startRiskLevels = [RiskLevel::LOW->value, RiskLevel::MEDIUM->value];

    $sourceCourse->riskBasedRequirements()->attach($requirement, [
        'course_validity_types' => json_encode($validityTypes),
        'integrative_start_risk_levels' => json_encode($startRiskLevels),
    ]);

    $duplicates = [
        app(DuplicateCourse::class)->handle($sourceCourse),
        app(DuplicateCourseStructure::class)->handle($sourceCourse, 'CRS-'.fake()->unique()->numerify('########')),
    ];

    foreach ($duplicates as $duplicate) {
        $pivot = $duplicate->riskBasedRequirements()->firstOrFail()->pivot;

        expect($pivot->course_validity_types)->toBe($validityTypes)
            ->and($pivot->integrative_start_risk_levels)->toBe($startRiskLevels);
    }
});
