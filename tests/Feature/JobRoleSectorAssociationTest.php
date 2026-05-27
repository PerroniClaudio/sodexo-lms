<?php

use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\NaceAteco;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('job-role-sector-association');

beforeEach(function () {
    actingAsRole('superadmin');
});

test('can view sector associations in job role edit page', function () {
    $role = JobRole::factory()->create(['name' => 'Preposto']);
    $sector = JobSector::factory()->create(['name' => 'Test Sector']);

    $role->jobSectors()->attach($sector->id, ['role_risk_level' => RiskLevel::HIGH->value]);

    $response = $this->get(route('admin.job-roles.edit', $role));

    $response->assertOk()
        ->assertSee('Test Sector')
        ->assertSee('Associazioni Settore-Rischio');
});

test('can attach sector to job role with risk level', function () {
    $role = JobRole::factory()->create();
    $sector = JobSector::factory()->create();

    $response = $this->post(route('admin.job-roles.sectors.attach', $role), [
        'job_sector_id' => $sector->id,
        'role_risk_level' => RiskLevel::HIGH->value,
    ]);

    $response->assertRedirect(route('admin.job-roles.edit', $role))
        ->assertSessionHas('status');

    expect($role->jobSectors()->count())->toBe(1);
    expect($role->jobSectors()->first()->pivot->role_risk_level)->toBe(RiskLevel::HIGH->value);
});

test('cannot attach same sector twice', function () {
    $role = JobRole::factory()->create();
    $sector = JobSector::factory()->create();

    $role->jobSectors()->attach($sector->id, ['role_risk_level' => RiskLevel::MEDIUM->value]);

    $response = $this->post(route('admin.job-roles.sectors.attach', $role), [
        'job_sector_id' => $sector->id,
        'role_risk_level' => RiskLevel::HIGH->value,
    ]);

    $response->assertRedirect(route('admin.job-roles.edit', $role))
        ->assertSessionHas('error');

    expect($role->jobSectors()->count())->toBe(1);
});

test('can detach sector from job role', function () {
    $role = JobRole::factory()->create();
    $sector = JobSector::factory()->create();

    $role->jobSectors()->attach($sector->id, ['role_risk_level' => RiskLevel::MEDIUM->value]);

    expect($role->jobSectors()->count())->toBe(1);

    $response = $this->delete(route('admin.job-roles.sectors.detach', [$role, $sector]));

    $response->assertRedirect(route('admin.job-roles.edit', $role))
        ->assertSessionHas('status');

    expect($role->fresh()->jobSectors()->count())->toBe(0);
});

test('can update sector risk level for job role', function () {
    $role = JobRole::factory()->create();
    $sector = JobSector::factory()->create();

    $role->jobSectors()->attach($sector->id, ['role_risk_level' => RiskLevel::LOW->value]);

    $response = $this->put(route('admin.job-roles.sectors.update', [$role, $sector]), [
        'role_risk_level' => RiskLevel::HIGH->value,
    ]);

    $response->assertRedirect(route('admin.job-roles.edit', $role))
        ->assertSessionHas('status');

    expect($role->fresh()->jobSectors()->first()->pivot->role_risk_level)->toBe(RiskLevel::HIGH->value);
});

test('effective risk is calculated correctly for role-sector association', function () {
    $sectionQ = NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => 1,
        'title_it' => 'Sanità',
        'title_en' => 'Health',
        'risk' => RiskLevel::MEDIUM->value,
    ]);
    $sector = JobSector::factory()->create(['name' => 'Healthcare Sector']);
    $sector->naceAtecoCodes()->attach($sectionQ->code, ['inclusion_type' => InclusionType::SECTION->value]);

    $role = JobRole::factory()->create(['name' => 'Preposto']);
    $role->jobSectors()->attach($sector->id, ['role_risk_level' => RiskLevel::HIGH->value]);

    $effectiveRisk = $sector->getEffectiveWorkerRisk($role->id);

    expect($effectiveRisk)->toBe(RiskLevel::HIGH);
});

test('effective risk uses sector risk when role risk is lower', function () {
    $sectionR = NaceAteco::create([
        'section' => 'R',
        'code' => 'R',
        'order' => 1,
        'hierarchy' => 1,
        'title_it' => 'Attività artistiche',
        'title_en' => 'Arts and entertainment',
        'risk' => RiskLevel::HIGH->value,
    ]);
    $sector = JobSector::factory()->create(['name' => 'Arts and Entertainment']);
    $sector->naceAtecoCodes()->attach($sectionR->code, ['inclusion_type' => InclusionType::SECTION->value]);

    $role = JobRole::factory()->create(['name' => 'Lavoratore']);
    $role->jobSectors()->attach($sector->id, ['role_risk_level' => RiskLevel::LOW->value]);

    $effectiveRisk = $sector->getEffectiveWorkerRisk($role->id);

    expect($effectiveRisk)->toBe(RiskLevel::HIGH);
});
