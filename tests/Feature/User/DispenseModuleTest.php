<?php

use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\DispenseDownload;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleTeachingMaterial;
use App\Services\ModuleValidation\ModuleValidatorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{Course, Module, CourseEnrollment, ModuleTeachingMaterial, ModuleTeachingMaterial}
 */
function dispenseContext(int $minimumDurationSeconds = 0, bool $withNextModule = false): array
{
    $user = actingAsRole('user');
    $course = Course::factory()->create(['type' => 'blended']);
    $module = Module::factory()->create([
        'type' => Module::TYPE_DISPENSE,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
        'minimum_duration_seconds' => $minimumDurationSeconds,
    ]);

    expect(app(ModuleValidatorService::class)->validate($module))->toBeFalse();

    if ($withNextModule) {
        Module::factory()->create([
            'type' => Module::TYPE_RESIDENTIAL,
            'order' => 2,
            'belongsTo' => (string) $course->getKey(),
        ]);
    }

    $materials = collect(['prima.pdf', 'seconda.docx'])->map(fn (string $name): ModuleTeachingMaterial => $module->teachingMaterials()->create([
        'uploaded_by' => $user->getKey(),
        'disk' => 's3',
        'path' => 'modules/'.$module->getKey().'/teaching-materials/'.$name,
        'original_name' => $name,
        'mime_type' => $name === 'prima.pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 12,
        'uploaded_at' => now(),
    ]));

    $materials->each(fn (ModuleTeachingMaterial $material) => Storage::disk('s3')->put($material->path, 'file'));

    return [$course, $module, CourseEnrollment::enroll($user, $course), $materials[0], $materials[1]];
}

beforeEach(function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    $this->withoutVite();
});

it('allows dispense only for blended and both fad course types', function () {
    expect((new Course(['type' => 'blended']))->allowsModuleType(Module::TYPE_DISPENSE))->toBeTrue()
        ->and((new Course(['type' => 'fad']))->allowsModuleType(Module::TYPE_DISPENSE))->toBeTrue()
        ->and((new Course(['type' => 'async']))->allowsModuleType(Module::TYPE_DISPENSE))->toBeTrue()
        ->and((new Course(['type' => 'res']))->allowsModuleType(Module::TYPE_DISPENSE))->toBeFalse()
        ->and((new Course(['type' => 'fsc']))->allowsModuleType(Module::TYPE_DISPENSE))->toBeFalse();
});

it('stores dispense files and converts its duration to seconds', function () {
    actingAsRole('admin');
    $course = Course::factory()->create(['type' => 'blended']);
    $module = Module::factory()->create([
        'type' => Module::TYPE_DISPENSE,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this->post(route('admin.courses.modules.teaching-materials.store', [$course, $module]), [
        'materials' => [
            UploadedFile::fake()->create('dispensa.pdf', 64, 'application/pdf'),
            UploadedFile::fake()->create('allegati.zip', 64, 'application/zip'),
        ],
    ])->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));

    $this->put(route('admin.courses.modules.update', [$course, $module]), [
        'title' => 'Dispense sicurezza',
        'description' => '',
        'status' => 'draft',
        'access_delay_minutes' => 0,
        'minimum_duration_hours' => 1,
        'minimum_duration_minutes' => 2,
        'minimum_duration_seconds' => 3,
    ])->assertRedirect(route('admin.courses.modules.edit', [$course, $module]));

    expect($module->fresh()->minimum_duration_seconds)->toBe(3723)
        ->and($module->teachingMaterials()->count())->toBe(2)
        ->and(app(ModuleValidatorService::class)->validate($module))->toBeTrue();
});

it('renders the dispense player and tracks downloads idempotently with an audit per request', function () {
    [$course, $module, $enrollment, $firstMaterial] = dispenseContext();

    $this->get(route('user.courses.modules.player', [$course, $module]))
        ->assertOk()
        ->assertSeeText('Dispense')
        ->assertSeeText('prima.pdf')
        ->assertSeeText('seconda.docx');

    $downloadRoute = route('user.courses.modules.dispense.download', [$course, $module, $firstMaterial]);
    $this->get($downloadRoute)->assertOk()->assertDownload('prima.pdf');
    $this->get($downloadRoute)->assertOk()->assertDownload('prima.pdf');

    expect(DispenseDownload::query()
        ->where('course_enrollment_id', $enrollment->getKey())
        ->where('module_teaching_material_id', $firstMaterial->getKey())
        ->count())->toBe(1)
        ->and(AuditEvent::query()
            ->where('actor_user_id', auth()->id())
            ->where('origin', 'user_ui')
            ->where('action', 'downloaded')
            ->where('subject_type', 'ModuleTeachingMaterial')
            ->where('subject_id', $firstMaterial->getKey())
            ->count())->toBe(2);
});

it('blocks unavailable users and completion before every file is downloaded', function () {
    [$course, $module, $enrollment, $firstMaterial] = dispenseContext();

    $this->postJson(route('user.courses.modules.dispense.complete', [$course, $module]))
        ->assertUnprocessable()
        ->assertJsonPath('all_downloaded', false);

    $enrollment->moduleProgresses()->where('module_id', $module->getKey())->update(['status' => ModuleProgress::STATUS_LOCKED]);

    $this->get(route('user.courses.modules.dispense.download', [$course, $module, $firstMaterial]))
        ->assertForbidden();
});

it('waits from the last first download before allowing progress', function () {
    [$course, $module, $enrollment, $firstMaterial, $secondMaterial] = dispenseContext(60, true);

    foreach ([$firstMaterial, $secondMaterial] as $material) {
        $this->get(route('user.courses.modules.dispense.download', [$course, $module, $material]))
            ->assertOk();
    }

    $this->getJson(route('user.courses.modules.dispense.status', [$course, $module]))
        ->assertOk()
        ->assertJsonPath('all_downloaded', true)
        ->assertJsonPath('can_proceed', false);

    $this->postJson(route('user.courses.modules.dispense.complete', [$course, $module]))
        ->assertUnprocessable();

    DispenseDownload::query()
        ->where('course_enrollment_id', $enrollment->getKey())
        ->update(['downloaded_at' => now()->subSeconds(61)]);

    $this->postJson(route('user.courses.modules.dispense.complete', [$course, $module]))
        ->assertOk()
        ->assertJsonPath('completed', true);

    expect($enrollment->fresh()->current_module_id)->not->toBe($module->getKey())
        ->and($enrollment->moduleProgresses()->where('module_id', $module->getKey())->value('status'))->toBe(ModuleProgress::STATUS_COMPLETED);
});

it('completes the course immediately after all downloads when duration is zero', function () {
    [$course, $module, $enrollment, $firstMaterial, $secondMaterial] = dispenseContext();

    foreach ([$firstMaterial, $secondMaterial] as $material) {
        $this->get(route('user.courses.modules.dispense.download', [$course, $module, $material]))
            ->assertOk();
    }

    $this->getJson(route('user.courses.modules.dispense.status', [$course, $module]))
        ->assertOk()
        ->assertJsonPath('can_proceed', true)
        ->assertJsonPath('remaining_seconds', 0);

    $this->postJson(route('user.courses.modules.dispense.complete', [$course, $module]))
        ->assertOk();

    expect($enrollment->fresh()->status)->toBe(CourseEnrollment::STATUS_COMPLETED)
        ->and($enrollment->fresh()->completion_percentage)->toBe(100);
});
