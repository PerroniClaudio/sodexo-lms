<?php

use App\Models\RiskBasedRequirement;
use Database\Seeders\RiskBasedRequirementSeeder;

it('seeds the expected default risk based requirements', function () {
    $this->seed(RiskBasedRequirementSeeder::class);

    expect(RiskBasedRequirement::query()->count())->toBe(4);

    $generalRequirement = RiskBasedRequirement::query()
        ->where('name', 'Formazione Generale')
        ->firstOrFail();

    expect($generalRequirement->description)->toBe('Obbligatoria per tutti i lavoratori, validità illimitata.')
        ->and($generalRequirement->is_limited_validity)->toBeFalse()
        ->and($generalRequirement->validity_months)->toBeNull()
        ->and($generalRequirement->reset_formation_years)->toBeNull();

    $highRiskRequirement = RiskBasedRequirement::query()
        ->where('name', 'Formazione Specifica Rischio Alto')
        ->firstOrFail();

    expect($highRiskRequirement->is_limited_validity)->toBeTrue()
        ->and($highRiskRequirement->validity_months)->toBe(60)
        ->and($highRiskRequirement->reset_formation_years)->toBe(10);
});
