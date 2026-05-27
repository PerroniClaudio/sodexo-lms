<?php

use App\Enums\HierarchyLevel;
use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\NaceAteco;
use App\Services\RiskCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RiskCalculationService;
});

test('getSectorRiskLevel returns highest risk from ATECO codes', function () {
    $code8690 = NaceAteco::create([
        'section' => 'Q',
        'code' => '86.90',
        'order' => 3,
        'hierarchy' => HierarchyLevel::NACE_CLASS->value,
        'title_it' => 'Altre attività di assistenza sanitaria',
        'title_en' => 'Other human health activities',
        'risk' => RiskLevel::MEDIUM->value,
    ]);

    $jobSector = JobSector::create([
        'name' => 'Sanità',
        'code' => 'SANITA',
        'description' => 'Settore sanitario',
    ]);

    $jobSector->naceAtecoCodes()->attach($code8690->code, [
        'inclusion_type' => InclusionType::NACE_CLASS->value,
    ]);

    $result = $this->service->getSectorRiskLevel($jobSector->id);

    expect($result)->toBe(RiskLevel::MEDIUM);
});

test('getSectorRiskLevel returns highest risk when including entire section', function () {
    NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SECTION->value,
        'title_it' => 'Sanità',
        'title_en' => 'Health',
        'risk' => RiskLevel::HIGH->value,
    ]);

    NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 2,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health',
        'risk' => RiskLevel::MEDIUM->value,
    ]);

    $jobSector = JobSector::create([
        'name' => 'Sanità Generale',
        'code' => 'SANITA_GEN',
        'description' => 'Tutto il settore sanitario',
    ]);

    $jobSector->naceAtecoCodes()->attach('Q', [
        'inclusion_type' => InclusionType::SECTION->value,
    ]);

    $result = $this->service->getSectorRiskLevel($jobSector->id);

    expect($result)->toBe(RiskLevel::HIGH);
});

test('getEffectiveWorkerRisk returns highest between sector and role risk', function () {
    $code86 = NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 1,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health',
        'risk' => RiskLevel::MEDIUM->value,
    ]);

    $jobSector = JobSector::create([
        'name' => 'Sanità',
        'code' => 'SANITA',
        'description' => 'Settore sanitario',
    ]);

    $jobSector->naceAtecoCodes()->attach($code86->code, [
        'inclusion_type' => InclusionType::DIVISION->value,
    ]);

    $jobRole = JobRole::create([
        'name' => 'Preposto',
        'description' => 'Ruolo con responsabilità di vigilanza',
    ]);

    $jobRole->jobSectors()->attach($jobSector->id, [
        'role_risk_level' => RiskLevel::HIGH->value,
    ]);

    $result = $this->service->getEffectiveWorkerRisk($jobSector->id, $jobRole->id);

    expect($result)->toBe(RiskLevel::HIGH);
});

test('getEffectiveWorkerRisk returns sector risk when no role mapping exists', function () {
    $code86 = NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 1,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $jobSector = JobSector::create([
        'name' => 'Sanità',
        'code' => 'SANITA',
        'description' => 'Settore sanitario',
    ]);

    $jobSector->naceAtecoCodes()->attach($code86->code, [
        'inclusion_type' => InclusionType::DIVISION->value,
    ]);

    $jobRole = JobRole::create([
        'name' => 'Lavoratore',
        'description' => 'Ruolo base',
    ]);

    $result = $this->service->getEffectiveWorkerRisk($jobSector->id, $jobRole->id);

    expect($result)->toBe(RiskLevel::HIGH);
});

test('findSectorByAtecoCode finds sector by full code', function () {
    $code869011 = NaceAteco::create([
        'section' => 'Q',
        'code' => '86.90.11',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SUBCATEGORY->value,
        'title_it' => 'Servizi degli istituti di cura',
        'title_en' => 'Hospital services',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $jobSector = JobSector::create([
        'name' => 'Ospedali',
        'code' => 'OSPEDALI',
        'description' => 'Servizi ospedalieri',
    ]);

    $jobSector->naceAtecoCodes()->attach($code869011->code, [
        'inclusion_type' => InclusionType::FULL_CODE->value,
    ]);

    $result = $this->service->findSectorByAtecoCode('86.90.11');

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($jobSector->id);
});

test('findSectorByAtecoCode finds sector by hierarchical parent', function () {
    NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SECTION->value,
        'title_it' => 'Sanità',
        'title_en' => 'Health',
        'risk' => RiskLevel::HIGH->value,
    ]);

    NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 2,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health',
        'risk' => RiskLevel::MEDIUM->value,
    ]);

    NaceAteco::create([
        'section' => 'Q',
        'code' => '86.90.11',
        'order' => 3,
        'hierarchy' => HierarchyLevel::SUBCATEGORY->value,
        'title_it' => 'Servizi degli istituti di cura',
        'title_en' => 'Hospital services',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $jobSector = JobSector::create([
        'name' => 'Sanità',
        'code' => 'SANITA',
        'description' => 'Tutto il settore sanitario',
    ]);

    $jobSector->naceAtecoCodes()->attach('Q', [
        'inclusion_type' => InclusionType::SECTION->value,
    ]);

    $result = $this->service->findSectorByAtecoCode('86.90.11');

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($jobSector->id);
});

test('getSectionForCode returns section record', function () {
    NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SECTION->value,
        'title_it' => 'Sanità e assistenza sociale',
        'title_en' => 'Human health and social work',
        'risk' => RiskLevel::HIGH->value,
    ]);

    NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 2,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health',
        'risk' => RiskLevel::MEDIUM->value,
    ]);

    $result = $this->service->getSectionForCode('86');

    expect($result)->not->toBeNull()
        ->and($result->code)->toBe('Q')
        ->and($result->title_it)->toBe('Sanità e assistenza sociale');
});
