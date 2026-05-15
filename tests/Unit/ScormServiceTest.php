<?php

use App\Models\Course;
use App\Models\Module;
use App\Services\ScormService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('extracts and parses a valid SCORM package using the default organization entry point', function () {
    Storage::fake('local');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'title' => 'Modulo SCORM',
        'type' => 'scorm',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
        'other/index.html' => '<html><body>Other lesson</body></html>',
    ]));

    expect($package->status)->toBe('ready');
    expect($package->version)->toBe('1.2');
    expect($package->identifier)->toBe('manifest-1');
    expect($package->entry_point)->toBe('lesson/index.html');
    expect($package->manifest_data)->toBeArray();
    expect($package->sco_data)->toBeArray();
    expect(Storage::disk('local')->exists($package->file_path))->toBeTrue();
    expect(Storage::disk('local')->exists($package->extracted_path.'/lesson/index.html'))->toBeTrue();
});

it('rejects invalid SCORM archives without a manifest', function () {
    Storage::fake('local');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'belongsTo' => (string) $course->getKey(),
    ]);

    expect(fn () => app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'lesson/index.html' => '<html><body>Missing manifest</body></html>',
    ])))->toThrow(RuntimeException::class, 'imsmanifest.xml');
});

it('rejects archives containing path traversal entries', function () {
    Storage::fake('local');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'belongsTo' => (string) $course->getKey(),
    ]);

    expect(fn () => app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        '../imsmanifest.xml' => validScormManifest(),
        'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
    ])))->toThrow(RuntimeException::class, 'not allowed');
});

it('accepts directory entries and ignores macOS metadata inside SCORM archives', function () {
    Storage::fake('local');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'title' => 'Modulo SCORM',
        'type' => 'scorm',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $package = app(ScormService::class)->storeUploadedPackage($module, scormZipUpload([
        'Etiquette/' => null,
        '__MACOSX/' => null,
        '__MACOSX/._imsmanifest.xml' => 'metadata',
        'Etiquette/imsmanifest.xml' => validScormManifest(),
        'Etiquette/lesson/index.html' => '<html><body>SCORM lesson</body></html>',
        'Etiquette/.DS_Store' => 'metadata',
        'Etiquette/other/index.html' => '<html><body>Other lesson</body></html>',
    ]));

    expect($package->status)->toBe('ready');
    expect($package->entry_point)->toBe('Etiquette/lesson/index.html');
    expect(Storage::disk('local')->exists($package->extracted_path.'/Etiquette/lesson/index.html'))->toBeTrue();
    expect(Storage::disk('local')->exists($package->extracted_path.'/__MACOSX'))->toBeFalse();
    expect(Storage::disk('local')->exists($package->extracted_path.'/Etiquette/.DS_Store'))->toBeFalse();
});
