<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoTrackingEvent;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function videoModuleWithTeachingMaterial(): array
{
    $user = actingAsRole('user');
    $course = Course::factory()->create();
    $video = Video::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
        'video_id' => $video->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);
    $material = $module->teachingMaterials()->create([
        'uploaded_by' => $user->getKey(),
        'disk' => 's3',
        'path' => 'modules/'.$module->getKey().'/teaching-materials/dispensa.pdf',
        'original_name' => 'dispensa.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 12,
        'uploaded_at' => now(),
    ]);

    Storage::disk('s3')->put($material->path, 'pdf');

    return [$user, $course, $module, $enrollment, $material];
}

beforeEach(function () {
    Storage::fake('s3');
    $this->withoutVite();
});

it('shows teaching materials on video module player', function () {
    [, $course, $module, , $material] = videoModuleWithTeachingMaterial();

    $this->get(route('user.courses.modules.player', [$course, $module]))
        ->assertOk()
        ->assertSeeText('Materiale didattico')
        ->assertSeeText($material->original_name)
        ->assertSee(route('user.courses.modules.video.teaching-materials.download', [$course, $module, $material]), false);
});

it('downloads teaching materials for enrolled users and records audit event', function () {
    [, $course, $module, $enrollment, $material] = videoModuleWithTeachingMaterial();

    $this->get(route('user.courses.modules.video.teaching-materials.download', [$course, $module, $material]))
        ->assertOk()
        ->assertHeader('content-disposition');

    $event = VideoTrackingEvent::query()
        ->where('event_type', VideoTrackingEvent::TYPE_TEACHING_MATERIAL_DOWNLOADED)
        ->firstOrFail();

    expect($event->course_user_id)->toBe($enrollment->getKey())
        ->and($event->module_id)->toBe($module->getKey())
        ->and($event->video_id)->toBe($module->video_id)
        ->and($event->client_payload['material_id'])->toBe($material->getKey());
});

it('does not allow another user to download teaching materials', function () {
    [, $course, $module, , $material] = videoModuleWithTeachingMaterial();
    $otherUser = User::query()->create([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => strtoupper(Str::random(16)),
        'is_foreigner_or_immigrant' => false,
    ]);
    $otherUser->assignRole('user');

    $this->actingAs($otherUser);

    $this->get(route('user.courses.modules.video.teaching-materials.download', [$course, $module, $material]))
        ->assertForbidden();
});

it('does not allow downloading materials when module progress is locked', function () {
    [, $course, $module, $enrollment, $material] = videoModuleWithTeachingMaterial();

    $enrollment->moduleProgresses()
        ->where('module_id', $module->getKey())
        ->firstOrFail()
        ->forceFill(['status' => ModuleProgress::STATUS_LOCKED])
        ->save();

    $this->get(route('user.courses.modules.video.teaching-materials.download', [$course, $module, $material]))
        ->assertForbidden();
});
