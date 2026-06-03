<?php

use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\NaceAteco;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses()->group('job-task-sector-association');

beforeEach(function () {
    actingAsRole('superadmin');
});

test('can view sector associations in job task edit page', function () {
    $task = JobTask::factory()->create(['name' => 'Infermiere']);
    $sector = JobSector::factory()->create(['name' => 'Test Sector']);

    $task->jobSectors()->attach($sector->id, ['task_risk_level' => RiskLevel::HIGH->value]);

    $response = $this->get(route('admin.job-tasks.edit', $task));

    $response->assertOk()
        ->assertSee('Test Sector')
        ->assertSee('Associazioni Mansione-Rischio');
});

test('job task edit page uses searchable selector for sector association', function () {
    $task = JobTask::factory()->create(['name' => 'Infermiere']);
    JobSector::factory()->create(['name' => 'Logistica']);

    $response = $this->get(route('admin.job-tasks.edit', $task));

    $response->assertOk()
        ->assertSee('data-searchable-select="job_sector_id_', escape: false)
        ->assertSee('placeholder="Cerca o seleziona un settore..."', escape: false);
});

test('job sector edit page uses searchable selector for ateco association', function () {
    $sector = JobSector::factory()->create(['name' => 'Logistica']);

    NaceAteco::create([
        'section' => 'H',
        'code' => 'H',
        'order' => 1,
        'hierarchy' => 1,
        'title_it' => 'Trasporto e magazzinaggio',
        'title_en' => 'Transportation and storage',
        'risk' => RiskLevel::MEDIUM->value,
    ]);

    $response = $this->get(route('admin.job-sectors.edit', $sector));

    $response->assertOk()
        ->assertSee('data-searchable-select="nace_ateco_code_', escape: false)
        ->assertSee('placeholder="Cerca o seleziona un codice ATECO..."', escape: false);
});

test('can attach sector to job task with risk level', function () {
    $task = JobTask::factory()->create();
    $sector = JobSector::factory()->create();

    $response = $this->post(route('admin.job-tasks.sectors.attach', $task), [
        'job_sector_id' => $sector->id,
        'task_risk_level' => RiskLevel::HIGH->value,
        'sector_risk_override' => 1,
    ]);

    $response->assertRedirect(route('admin.job-tasks.edit', $task))
        ->assertSessionHas('status');

    expect($task->jobSectors()->count())->toBe(1);
    expect($task->jobSectors()->first()->pivot->task_risk_level)->toBe(RiskLevel::HIGH->value);
    expect((bool) $task->jobSectors()->first()->pivot->sector_risk_override)->toBeTrue();
});

test('cannot attach same sector twice', function () {
    $task = JobTask::factory()->create();
    $sector = JobSector::factory()->create();

    $task->jobSectors()->attach($sector->id, ['task_risk_level' => RiskLevel::MEDIUM->value]);

    $response = $this->post(route('admin.job-tasks.sectors.attach', $task), [
        'job_sector_id' => $sector->id,
        'task_risk_level' => RiskLevel::HIGH->value,
    ]);

    $response->assertRedirect(route('admin.job-tasks.edit', $task))
        ->assertSessionHas('error');

    expect($task->jobSectors()->count())->toBe(1);
});

test('can detach sector from job task', function () {
    $task = JobTask::factory()->create();
    $sector = JobSector::factory()->create();

    $task->jobSectors()->attach($sector->id, ['task_risk_level' => RiskLevel::MEDIUM->value]);

    expect($task->jobSectors()->count())->toBe(1);

    $response = $this->delete(route('admin.job-tasks.sectors.detach', [$task, $sector]));

    $response->assertRedirect(route('admin.job-tasks.edit', $task))
        ->assertSessionHas('status');

    expect($task->fresh()->jobSectors()->count())->toBe(0);
});

test('can update sector risk level for job task', function () {
    $task = JobTask::factory()->create();
    $sector = JobSector::factory()->create();

    $task->jobSectors()->attach($sector->id, ['task_risk_level' => RiskLevel::LOW->value]);

    $response = $this->put(route('admin.job-tasks.sectors.update', [$task, $sector]), [
        'task_risk_level' => RiskLevel::HIGH->value,
        'sector_risk_override' => 1,
    ]);

    $response->assertRedirect(route('admin.job-tasks.edit', $task))
        ->assertSessionHas('status');

    expect($task->fresh()->jobSectors()->first()->pivot->task_risk_level)->toBe(RiskLevel::HIGH->value);
    expect((bool) $task->fresh()->jobSectors()->first()->pivot->sector_risk_override)->toBeTrue();
});

test('effective risk is calculated correctly for task-sector association', function () {
    $sectionQ = NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => 1,
        'title_it' => 'Sanita',
        'title_en' => 'Health',
        'risk' => RiskLevel::MEDIUM->value,
    ]);
    $sector = JobSector::factory()->create(['name' => 'Healthcare Sector']);
    $sector->naceAtecoCodes()->attach($sectionQ->code, ['inclusion_type' => InclusionType::SECTION->value]);

    $task = JobTask::factory()->create(['name' => 'Infermiere']);
    $task->jobSectors()->attach($sector->id, ['task_risk_level' => RiskLevel::HIGH->value]);

    $effectiveRisk = $sector->getEffectiveWorkerRisk($task->id);

    expect($effectiveRisk)->toBe(RiskLevel::HIGH);
});

test('effective risk uses sector risk when task risk is lower', function () {
    $sectionR = NaceAteco::create([
        'section' => 'R',
        'code' => 'R',
        'order' => 1,
        'hierarchy' => 1,
        'title_it' => 'Attivita artistiche',
        'title_en' => 'Arts and entertainment',
        'risk' => RiskLevel::HIGH->value,
    ]);
    $sector = JobSector::factory()->create(['name' => 'Arts and Entertainment']);
    $sector->naceAtecoCodes()->attach($sectionR->code, ['inclusion_type' => InclusionType::SECTION->value]);

    $task = JobTask::factory()->create(['name' => 'Archivista']);
    $task->jobSectors()->attach($sector->id, ['task_risk_level' => RiskLevel::LOW->value]);

    $effectiveRisk = $sector->getEffectiveWorkerRisk($task->id);

    expect($effectiveRisk)->toBe(RiskLevel::HIGH);
});

test('job task edit page shows override controls for sector associations', function () {
    $task = JobTask::factory()->create(['name' => 'Infermiere']);
    $sector = JobSector::factory()->create(['name' => 'Test Sector']);

    $task->jobSectors()->attach($sector->id, [
        'task_risk_level' => RiskLevel::MEDIUM->value,
        'sector_risk_override' => true,
    ]);

    $response = $this->get(route('admin.job-tasks.edit', $task));

    $response->assertOk()
        ->assertSee('Sovrascrive il rischio del settore anche se inferiore')
        ->assertSee('Override settore');
});
