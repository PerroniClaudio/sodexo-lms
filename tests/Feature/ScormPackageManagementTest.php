<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('uploads a valid SCORM package from the admin area', function () {
    Storage::fake('local');
    actingAsRole('admin');
    $this->withoutVite();

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->post(route('admin.courses.modules.scorm.store', [$course, $module]), [
        'package' => scormZipUpload([
            'imsmanifest.xml' => validScormManifest(),
            'lesson/index.html' => '<html><body>SCORM lesson</body></html>',
        ]),
        'title' => 'Pacchetto sicurezza',
    ]);

    $response
        ->assertRedirect(route('admin.courses.modules.scorm.index', [$course, $module]))
        ->assertSessionHas('status');

    $package = $module->scormPackages()->firstOrFail();

    expect($package->title)->toBe('Pacchetto sicurezza');
    expect($package->status)->toBe('ready');
    expect($package->entry_point)->toBe('lesson/index.html');
});

it('rejects invalid SCORM uploads from the admin area', function () {
    Storage::fake('local');
    actingAsRole('admin');
    $this->withoutVite();

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'scorm',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->from(route('admin.courses.modules.scorm.index', [$course, $module]))
        ->post(route('admin.courses.modules.scorm.store', [$course, $module]), [
            'package' => scormZipUpload([
                'lesson/index.html' => '<html><body>Missing manifest</body></html>',
            ]),
        ]);

    $response
        ->assertRedirect(route('admin.courses.modules.scorm.index', [$course, $module]))
        ->assertSessionHasErrors('package');

    expect($module->scormPackages()->count())->toBe(1);
    expect($module->scormPackages()->firstOrFail()->status)->toBe('error');
});
