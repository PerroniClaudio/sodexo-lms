<?php

use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Models\NaceAteco;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WorldDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('job-title-sector-association');

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(WorldDataSeeder::class);
    $this->actingAs(User::factory()->create()->assignRole('superadmin'));
});

test('can view sector associations in job title edit page', function () {
    $title = JobTitle::factory()->create(['name' => 'Test Title']);
    $sector = JobSector::factory()->create(['name' => 'Test Sector']);

    $title->jobSectors()->attach($sector->id, ['title_risk_level' => RiskLevel::HIGH->value]);

    $response = $this->get(route('admin.job-titles.edit', $title));

    $response->assertOk()
        ->assertSee('Test Sector')
        ->assertSee('Associazioni Settore-Rischio');
});

test('can attach sector to job title with risk level', function () {
    $title = JobTitle::factory()->create();
    $sector = JobSector::factory()->create();

    $response = $this->post(route('admin.job-titles.sectors.attach', $title), [
        'job_sector_id' => $sector->id,
        'title_risk_level' => RiskLevel::HIGH->value,
    ]);

    $response->assertRedirect(route('admin.job-titles.edit', $title))
        ->assertSessionHas('status');

    expect($title->jobSectors()->count())->toBe(1);
    expect($title->jobSectors()->first()->pivot->title_risk_level)->toBe(RiskLevel::HIGH->value);
});

test('cannot attach same sector twice', function () {
    $title = JobTitle::factory()->create();
    $sector = JobSector::factory()->create();

    $title->jobSectors()->attach($sector->id, ['title_risk_level' => RiskLevel::MEDIUM->value]);

    $response = $this->post(route('admin.job-titles.sectors.attach', $title), [
        'job_sector_id' => $sector->id,
        'title_risk_level' => RiskLevel::HIGH->value,
    ]);

    $response->assertRedirect(route('admin.job-titles.edit', $title))
        ->assertSessionHas('error');

    expect($title->jobSectors()->count())->toBe(1);
});

test('can detach sector from job title', function () {
    $title = JobTitle::factory()->create();
    $sector = JobSector::factory()->create();

    $title->jobSectors()->attach($sector->id, ['title_risk_level' => RiskLevel::MEDIUM->value]);

    expect($title->jobSectors()->count())->toBe(1);

    $response = $this->delete(route('admin.job-titles.sectors.detach', [$title, $sector]));

    $response->assertRedirect(route('admin.job-titles.edit', $title))
        ->assertSessionHas('status');

    expect($title->fresh()->jobSectors()->count())->toBe(0);
});

test('can update sector risk level for job title', function () {
    $title = JobTitle::factory()->create();
    $sector = JobSector::factory()->create();

    $title->jobSectors()->attach($sector->id, ['title_risk_level' => RiskLevel::LOW->value]);

    $response = $this->put(route('admin.job-titles.sectors.update', [$title, $sector]), [
        'title_risk_level' => RiskLevel::HIGH->value,
    ]);

    $response->assertRedirect(route('admin.job-titles.edit', $title))
        ->assertSessionHas('status');

    expect($title->fresh()->jobSectors()->first()->pivot->title_risk_level)->toBe(RiskLevel::HIGH->value);
});

test('effective risk is calculated correctly for title-sector association', function () {
    // Create a sector with MEDIUM risk (using Section Q codes)
    $sectionQ = NaceAteco::where('code', 'Q')->first();
    $sector = JobSector::factory()->create(['name' => 'Healthcare Sector']);
    $sector->naceAtecoCodes()->attach($sectionQ->code, ['inclusion_type' => InclusionType::SECTION->value]);

    // Create a title and associate it with HIGH risk for this sector
    $title = JobTitle::factory()->create(['name' => 'Surgeon']);
    $title->jobSectors()->attach($sector->id, ['title_risk_level' => RiskLevel::HIGH->value]);

    // The effective risk should be HIGH (max of MEDIUM and HIGH)
    $effectiveRisk = $sector->getEffectiveWorkerRisk($title->id);

    expect($effectiveRisk)->toBe(RiskLevel::HIGH);
});

test('effective risk uses sector risk when title risk is lower', function () {
    // Create a sector with HIGH risk (using Section R codes)
    $sectionR = NaceAteco::where('code', 'R')->first();
    $sector = JobSector::factory()->create(['name' => 'Arts and Entertainment']);
    $sector->naceAtecoCodes()->attach($sectionR->code, ['inclusion_type' => InclusionType::SECTION->value]);

    // Create a title and associate it with LOW risk for this sector
    $title = JobTitle::factory()->create(['name' => 'Ticket Seller']);
    $title->jobSectors()->attach($sector->id, ['title_risk_level' => RiskLevel::LOW->value]);

    // The effective risk should be HIGH (max of HIGH and LOW)
    $effectiveRisk = $sector->getEffectiveWorkerRisk($title->id);

    expect($effectiveRisk)->toBe(RiskLevel::HIGH);
});
