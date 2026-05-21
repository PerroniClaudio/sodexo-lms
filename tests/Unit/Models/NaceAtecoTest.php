<?php

use App\Enums\HierarchyLevel;
use App\Enums\RiskLevel;
use App\Models\JobSector;
use App\Models\NaceAteco;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('nace ateco can be created with all required fields', function () {
    $naceAteco = NaceAteco::create([
        'code' => 'TEST001',
        'order' => 1,
        'hierarchy' => HierarchyLevel::NACE_CLASS,
        'title_it' => 'Test Italiano',
        'title_en' => 'Test English',
        'risk' => RiskLevel::MEDIUM,
    ]);

    expect($naceAteco)->toBeInstanceOf(NaceAteco::class)
        ->and($naceAteco->code)->toBe('TEST001')
        ->and($naceAteco->hierarchy)->toBe(HierarchyLevel::NACE_CLASS)
        ->and($naceAteco->risk)->toBe(RiskLevel::MEDIUM);
});

test('isNace returns true for hierarchy 4', function () {
    $naceAteco = NaceAteco::create([
        'code' => 'NACE001',
        'order' => 1,
        'hierarchy' => HierarchyLevel::NACE_CLASS,
        'title_it' => 'Test NACE',
        'title_en' => 'Test NACE',
    ]);

    expect($naceAteco->isNace())->toBeTrue()
        ->and($naceAteco->isAteco())->toBeFalse();
});

test('isAteco returns true for hierarchy 6', function () {
    $naceAteco = NaceAteco::create([
        'code' => 'ATECO001',
        'order' => 1,
        'hierarchy' => HierarchyLevel::SUBCATEGORY,
        'title_it' => 'Test ATECO',
        'title_en' => 'Test ATECO',
    ]);

    expect($naceAteco->isAteco())->toBeTrue()
        ->and($naceAteco->isNace())->toBeFalse();
});

test('linkable scope returns only NACE and ATECO codes', function () {
    NaceAteco::create(['code' => 'S1', 'order' => 1, 'hierarchy' => HierarchyLevel::SECTION, 'title_it' => 'Section', 'title_en' => 'Section']);
    NaceAteco::create(['code' => 'D2', 'order' => 2, 'hierarchy' => HierarchyLevel::DIVISION, 'title_it' => 'Division', 'title_en' => 'Division']);
    NaceAteco::create(['code' => 'N4', 'order' => 3, 'hierarchy' => HierarchyLevel::NACE_CLASS, 'title_it' => 'NACE', 'title_en' => 'NACE']);
    NaceAteco::create(['code' => 'A6', 'order' => 4, 'hierarchy' => HierarchyLevel::SUBCATEGORY, 'title_it' => 'ATECO', 'title_en' => 'ATECO']);

    $linkable = NaceAteco::linkable()->get();

    expect($linkable)->toHaveCount(2)
        ->and($linkable->pluck('code')->toArray())->toContain('N4', 'A6');
});

test('job sector can be linked to nace ateco code', function () {
    $naceAteco = NaceAteco::create([
        'code' => 'LINK001',
        'order' => 1,
        'hierarchy' => HierarchyLevel::NACE_CLASS,
        'title_it' => 'Test Link',
        'title_en' => 'Test Link',
        'risk' => RiskLevel::HIGH,
    ]);

    $jobSector = JobSector::create([
        'name' => 'Test Sector',
        'code' => 'TST',
        'nace_ateco_code' => 'LINK001',
    ]);

    expect($jobSector->naceAteco)->toBeInstanceOf(NaceAteco::class)
        ->and($jobSector->naceAteco->code)->toBe('LINK001')
        ->and($jobSector->naceAteco->risk)->toBe(RiskLevel::HIGH);
});

test('job sector requires nace_ateco_code on creation', function () {
    JobSector::create([
        'name' => 'Test Sector',
        'code' => 'TST2',
    ]);
})->throws(InvalidArgumentException::class, 'Il campo nace_ateco_code è obbligatorio');

test('default title attribute returns italian title', function () {
    $naceAteco = NaceAteco::create([
        'code' => 'DEFAULT001',
        'order' => 1,
        'hierarchy' => HierarchyLevel::NACE_CLASS,
        'title_it' => 'Titolo Italiano',
        'title_en' => 'English Title',
    ]);

    expect($naceAteco->default_title)->toBe('Titolo Italiano');
});
