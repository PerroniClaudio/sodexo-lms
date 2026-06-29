<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleTeachingMaterial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('stores teaching materials for video modules', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->post(route('admin.courses.modules.teaching-materials.store', [$course, $module]), [
        'materials' => [
            UploadedFile::fake()->image('slide.png'),
            UploadedFile::fake()->create('dispensa.pdf', 128, 'application/pdf'),
            UploadedFile::fake()->create('presentazione.pptx', 128, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
        ],
    ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));

    expect(ModuleTeachingMaterial::query()->where('module_id', $module->getKey())->count())->toBe(3);

    ModuleTeachingMaterial::query()->where('module_id', $module->getKey())->each(function (ModuleTeachingMaterial $material): void {
        expect($material->path)->toStartWith('modules/'.$material->module_id.'/teaching-materials/');
        Storage::disk('s3')->assertExists($material->path);
    });
});

it('validates teaching material file types', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this->from(route('admin.courses.modules.edit', [$course, $module]))
        ->post(route('admin.courses.modules.teaching-materials.store', [$course, $module]), [
            'materials' => [
                UploadedFile::fake()->create('archive.zip', 64, 'application/zip'),
            ],
        ])
        ->assertRedirect(route('admin.courses.modules.edit', [$course, $module]))
        ->assertSessionHasErrors('materials.0');
});

it('does not allow teaching materials on non video modules', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_SCORM,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this->post(route('admin.courses.modules.teaching-materials.store', [$course, $module]), [
        'materials' => [
            UploadedFile::fake()->create('dispensa.pdf', 128, 'application/pdf'),
        ],
    ])->assertNotFound();
});

it('deletes teaching materials and stored files', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $material = $module->teachingMaterials()->create([
        'uploaded_by' => auth()->id(),
        'disk' => 's3',
        'path' => 'modules/'.$module->getKey().'/teaching-materials/dispensa.pdf',
        'original_name' => 'dispensa.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 12,
        'uploaded_at' => now(),
    ]);

    Storage::disk('s3')->put($material->path, 'pdf');

    $this->delete(route('admin.courses.modules.teaching-materials.destroy', [$course, $module, $material]))
        ->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));

    Storage::disk('s3')->assertMissing($material->path);
    expect(ModuleTeachingMaterial::query()->whereKey($material->getKey())->exists())->toBeFalse();
});

it('shows an error flash instead of deleting teaching materials from a published module', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'status' => 'published',
        'belongsTo' => (string) $course->getKey(),
    ]);
    $material = $module->teachingMaterials()->create([
        'uploaded_by' => auth()->id(),
        'disk' => 's3',
        'path' => 'modules/'.$module->getKey().'/teaching-materials/dispensa.pdf',
        'original_name' => 'dispensa.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 12,
        'uploaded_at' => now(),
    ]);

    Storage::disk('s3')->put($material->path, 'pdf');

    $this->delete(route('admin.courses.modules.teaching-materials.destroy', [$course, $module, $material]))
        ->assertRedirect(route('admin.courses.modules.edit', [$course, $module]))
        ->assertSessionHas('error', 'Non è possibile modificare o eliminare contenuti di un modulo pubblicato.');

    Storage::disk('s3')->assertExists($material->path);
    expect(ModuleTeachingMaterial::query()->whereKey($material->getKey())->exists())->toBeTrue();
});
