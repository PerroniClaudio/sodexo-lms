<?php

use App\Enums\HierarchyLevel;
use App\Enums\InclusionType;
use App\Enums\RiskLevel;
use App\Models\JobSector;
use App\Models\JobTitle;
use App\Models\NaceAteco;
use App\Services\RiskCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new RiskCalculationService;
});

test('getSectorRiskLevel returns highest risk from ATECO codes', function () {
    // Create NACE/ATECO codes with different risk levels
    $sectionQ = NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SECTION->value,
        'title_it' => 'Sanità e assistenza sociale',
        'title_en' => 'Human health and social work activities',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $code86 = NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 2,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health activities',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $code8690 = NaceAteco::create([
        'section' => 'Q',
        'code' => '86.90',
        'order' => 3,
        'hierarchy' => HierarchyLevel::NACE_CLASS->value,
        'title_it' => 'Altre attività di assistenza sanitaria',
        'title_en' => 'Other human health activities',
        'risk' => RiskLevel::MEDIUM->value,
    ]);

    // Create job sector
    $jobSector = JobSector::create([
        'name' => 'Sanità',
        'code' => 'SANITA',
        'description' => 'Settore sanitario',
    ]);

    // Attach NACE/ATECO codes
    $jobSector->naceAtecoCodes()->attach($code8690->code, [
        'inclusion_type' => InclusionType::NACE_CLASS->value,
    ]);

    // The sector should have MEDIUM risk (from code 86.90)
    $result = $this->service->getSectorRiskLevel($jobSector->id);

    expect($result)->toBe(RiskLevel::MEDIUM);
});

test('getSectorRiskLevel returns highest risk when including entire section', function () {
    // Create section with HIGH risk
    $sectionQ = NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SECTION->value,
        'title_it' => 'Sanità',
        'title_en' => 'Health',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $code86 = NaceAteco::create([
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

    // Include entire section Q
    $jobSector->naceAtecoCodes()->attach('Q', [
        'inclusion_type' => InclusionType::SECTION->value,
    ]);

    // Should return HIGH risk (highest in the section)
    $result = $this->service->getSectorRiskLevel($jobSector->id);

    expect($result)->toBe(RiskLevel::HIGH);
});

test('getEffectiveWorkerRisk returns highest between sector and title risk', function () {
    // Create ATECO code with MEDIUM risk
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

    $jobTitle = JobTitle::create([
        'name' => 'Infermiere',
        'code' => 'INFERMIERE',
        'description' => 'Professionista sanitario',
    ]);

    // Attach job title with HIGH risk
    $jobSector->jobTitles()->attach($jobTitle->id, [
        'title_risk_level' => RiskLevel::HIGH->value,
    ]);

    // Should return HIGH (max between MEDIUM sector and HIGH title)
    $result = $this->service->getEffectiveWorkerRisk($jobSector->id, $jobTitle->id);

    expect($result)->toBe(RiskLevel::HIGH);
});

test('getEffectiveWorkerRisk returns sector risk when no title mapping exists', function () {
    // Create ATECO code
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

    $jobTitle = JobTitle::create([
        'name' => 'Amministrativo',
        'code' => 'ADMIN',
        'description' => 'Personale amministrativo',
    ]);

    // No title mapping, should return sector risk (HIGH)
    $result = $this->service->getEffectiveWorkerRisk($jobSector->id, $jobTitle->id);

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
    // Create full hierarchy
    NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SECTION->value,
        'title_it' => 'Sanità',
        'title_en' => 'Health',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $code86 = NaceAteco::create([
        'section' => 'Q',
        'code' => '86',
        'order' => 2,
        'hierarchy' => HierarchyLevel::DIVISION->value,
        'title_it' => 'Assistenza sanitaria',
        'title_en' => 'Human health',
        'risk' => RiskLevel::MEDIUM->value,
    ]);

    $code869011 = NaceAteco::create([
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

    // Attach at section level
    $jobSector->naceAtecoCodes()->attach('Q', [
        'inclusion_type' => InclusionType::SECTION->value,
    ]);

    // Should find sector even when searching with specific code
    $result = $this->service->findSectorByAtecoCode('86.90.11');

    expect($result)->not->toBeNull()
        ->and($result->id)->toBe($jobSector->id);
});

test('getSectionForCode returns section record', function () {
    $sectionQ = NaceAteco::create([
        'section' => 'Q',
        'code' => 'Q',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SECTION->value,
        'title_it' => 'Sanità e assistenza sociale',
        'title_en' => 'Human health and social work',
        'risk' => RiskLevel::HIGH->value,
    ]);

    $code86 = NaceAteco::create([
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
