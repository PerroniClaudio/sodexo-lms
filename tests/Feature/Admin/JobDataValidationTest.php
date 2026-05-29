<?php

use App\Enums\RiskLevel;
use App\Http\Requests\StoreJobUnitRequest;
use App\Models\RiskBasedRequirement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('admin can create a risk based requirement without limited validity', function () {
    actingAsRole('admin');

    $response = $this->post(route('admin.risk-based-requirements.store'), [
        'name' => 'Requisito senza scadenza',
        'description' => 'Test validazione checkbox',
        'risk_levels' => [RiskLevel::LOW->value],
        'reset_formation_years' => 4,
    ]);

    $requirement = RiskBasedRequirement::query()->first();

    $response->assertRedirect(route('admin.risk-based-requirements.edit', $requirement));

    expect($requirement)
        ->not->toBeNull()
        ->and($requirement->is_limited_validity)->toBeFalse()
        ->and($requirement->validity_months)->toBeNull()
        ->and($requirement->reset_formation_years)->toBe(4);
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
