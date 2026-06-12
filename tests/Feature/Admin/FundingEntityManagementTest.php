<?php

use App\Models\FundingEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('allows admins to create a funding entity with only company name', function () {
    $response = $this->post(route('admin.funding-entities.store'), [
        'company_name' => 'Fondo Impresa Demo',
        'vat_number' => '',
        'fiscal_code' => '',
        'pec' => '',
    ]);

    $fundingEntity = FundingEntity::query()->firstOrFail();

    $response->assertRedirect(route('admin.funding-entities.edit', $fundingEntity));

    expect($fundingEntity->company_name)->toBe('Fondo Impresa Demo')
        ->and($fundingEntity->vat_number)->toBeNull()
        ->and($fundingEntity->fiscal_code)->toBeNull()
        ->and($fundingEntity->pec)->toBeNull();
});

it('allows admins to update optional funding entity fields', function () {
    $fundingEntity = FundingEntity::factory()->create([
        'company_name' => 'Ente iniziale',
        'vat_number' => null,
        'fiscal_code' => null,
        'pec' => null,
    ]);

    $response = $this->put(route('admin.funding-entities.update', $fundingEntity), [
        'company_name' => 'Ente aggiornato',
        'vat_number' => '12345678901',
        'fiscal_code' => 'RSSMRA80A01H501U',
        'pec' => 'ente@example.test',
    ]);

    $response->assertRedirect(route('admin.funding-entities.edit', $fundingEntity));

    expect($fundingEntity->fresh()->company_name)->toBe('Ente aggiornato')
        ->and($fundingEntity->fresh()->vat_number)->toBe('12345678901')
        ->and($fundingEntity->fresh()->fiscal_code)->toBe('RSSMRA80A01H501U')
        ->and($fundingEntity->fresh()->pec)->toBe('ente@example.test');
});

it('allows admins to delete and restore a funding entity', function () {
    $fundingEntity = FundingEntity::factory()->create();

    $deleteResponse = $this->delete(route('admin.funding-entities.destroy', $fundingEntity));

    $deleteResponse->assertRedirect(route('admin.funding-entities.index'));
    expect(FundingEntity::withTrashed()->findOrFail($fundingEntity->getKey())->trashed())->toBeTrue();

    $restoreResponse = $this->post(route('admin.funding-entities.restore', $fundingEntity->getKey()));

    $restoreResponse->assertRedirect(route('admin.funding-entities.index'));
    expect(FundingEntity::query()->findOrFail($fundingEntity->getKey())->trashed())->toBeFalse();
});

it('filters funding entities by company name and fiscal data in index', function () {
    FundingEntity::factory()->create([
        'company_name' => 'Alpha Formazione',
        'vat_number' => '11111111111',
        'fiscal_code' => 'ALPHAFORM123456',
        'pec' => 'alpha@example.test',
    ]);

    FundingEntity::factory()->create([
        'company_name' => 'Beta Academy',
        'vat_number' => '22222222222',
        'fiscal_code' => 'BETAACAD1234567',
        'pec' => 'beta@example.test',
    ]);

    $responseByName = $this->get(route('admin.funding-entities.index', ['search' => 'Alpha']));
    $responseByVat = $this->get(route('admin.funding-entities.index', ['search' => '22222222222']));
    $responseByPec = $this->get(route('admin.funding-entities.index', ['search' => 'alpha@example.test']));

    $responseByName->assertOk()->assertSeeText('Alpha Formazione')->assertDontSeeText('Beta Academy');
    $responseByVat->assertOk()->assertSeeText('Beta Academy')->assertDontSeeText('Alpha Formazione');
    $responseByPec->assertOk()->assertSeeText('Alpha Formazione')->assertDontSeeText('Beta Academy');
});
