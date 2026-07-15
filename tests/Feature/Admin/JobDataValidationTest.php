<?php

use App\Enums\RiskLevel;
use App\Http\Requests\StoreJobUnitRequest;
use App\Models\RiskBasedRequirement;
use Illuminate\Support\Facades\Validator;

test('admin can create a risk based requirement without limited validity', function () {
    actingAsRole('admin');

    $response = $this->post(route('admin.risk-based-requirements.store'), [
        'name' => 'Requisito senza scadenza',
        'description' => 'Test validazione checkbox',
        'training_family' => 'general',
        'risk_levels' => [RiskLevel::LOW->value],
        'reset_formation_years' => 4,
    ]);

    $requirement = RiskBasedRequirement::query()->first();

    $response->assertRedirect(route('admin.risk-based-requirements.edit', $requirement));

    expect($requirement)
        ->not->toBeNull()
        ->and($requirement->is_limited_validity)->toBeFalse()
        ->and($requirement->validity_months)->toBeNull()
        ->and($requirement->risk_progression_group)->toBeNull()
        ->and($requirement->reset_formation_years)->toBe(4);
});

test('admin can create a specific risk based requirement with automatic progression group', function () {
    actingAsRole('admin');

    $response = $this->post(route('admin.risk-based-requirements.store'), [
        'name' => 'Requisito specifico',
        'description' => 'Test famiglia formativa specifica',
        'training_family' => 'specific',
        'risk_levels' => [RiskLevel::HIGH->value],
        'reset_formation_years' => 5,
    ]);

    $requirement = RiskBasedRequirement::query()->firstWhere('name', 'Requisito specifico');

    $response->assertRedirect(route('admin.risk-based-requirements.edit', $requirement));

    expect($requirement)
        ->not->toBeNull()
        ->and($requirement->risk_progression_group)->toBe('worker_specific_training');
});

test('job unit validation accepts punctuation in unit code', function () {
    $request = new StoreJobUnitRequest;

    $validator = Validator::make([
        'name' => 'Sede Roma Centro',
        'unit_code' => 'ROMA-01/A',
        'description' => 'Test codice con punteggiatura',
        'country' => 'it',
        'region' => 'Lazio',
        'city' => 'Roma',
    ], $request->rules());

    expect($validator->fails())->toBeFalse();
});
