<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LanguageLevel;
use App\Models\Module;
use App\Models\User;
use App\Support\LanguageVerificationGate;
use Database\Seeders\LanguageLevelSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function (): void {
    $this->seed([
        RoleAndPermissionSeeder::class,
        LanguageLevelSeeder::class,
    ]);
});

it('blocks course access until required language verification course is completed', function () {
    $user = User::factory()->asUser()->create([
        'needs_language_level_verification' => true,
    ]);
    $this->actingAs($user);
    $a1 = LanguageLevel::query()->where('name', 'a1')->firstOrFail();
    $a2 = LanguageLevel::query()->where('name', 'a2')->firstOrFail();

    $user->update([
        'declared_language_level_id' => $a1->getKey(),
        'verified_language_level_id' => null,
        'needs_language_level_verification' => true,
    ]);

    $blockedCourse = Course::factory()->create([
        'title' => 'Corso Base',
        'required_language_level_id' => $a2->getKey(),
    ]);

    Module::factory()->create([
        'belongsTo' => (string) $blockedCourse->getKey(),
        'status' => 'published',
    ]);
    $blockedCourse->forceFill(['status' => 'published'])->saveQuietly();

    $verificationCourse = Course::factory()->create([
        'title' => 'Verifica A2',
        'required_language_level_id' => $a1->getKey(),
        'is_language_verification_course' => true,
        'grants_language_level_id' => $a2->getKey(),
    ]);

    Module::factory()->create([
        'belongsTo' => (string) $verificationCourse->getKey(),
        'status' => 'published',
    ]);
    $verificationCourse->forceFill(['status' => 'published'])->saveQuietly();

    CourseEnrollment::enroll($user, $blockedCourse);

    $response = $this->get(route('user.courses.show', $blockedCourse));

    $response
        ->assertSuccessful()
        ->assertSee('Verifica conoscenza della lingua')
        ->assertSee('Vai al corso di verifica');

    $verificationEnrollment = CourseEnrollment::query()
        ->where('user_id', $user->getKey())
        ->where('course_id', $verificationCourse->getKey())
        ->first();

    expect($verificationEnrollment)->not->toBeNull();
    expect((int) $verificationEnrollment->origin_course_id)->toBe((int) $blockedCourse->getKey());
});

it('updates verified language level and redirects back to origin course after verification completion', function () {
    $user = User::factory()->asUser()->create([
        'needs_language_level_verification' => true,
    ]);
    $this->actingAs($user);
    $a1 = LanguageLevel::query()->where('name', 'a1')->firstOrFail();
    $a2 = LanguageLevel::query()->where('name', 'a2')->firstOrFail();

    $user->update([
        'declared_language_level_id' => $a1->getKey(),
        'verified_language_level_id' => null,
        'needs_language_level_verification' => true,
    ]);

    $originCourse = Course::factory()->create([
        'required_language_level_id' => $a2->getKey(),
    ]);
    Module::factory()->create([
        'belongsTo' => (string) $originCourse->getKey(),
        'status' => 'published',
    ]);
    $originCourse->forceFill(['status' => 'published'])->saveQuietly();

    $verificationCourse = Course::factory()->create([
        'required_language_level_id' => $a1->getKey(),
        'is_language_verification_course' => true,
        'grants_language_level_id' => $a2->getKey(),
    ]);
    Module::factory()->create([
        'belongsTo' => (string) $verificationCourse->getKey(),
        'status' => 'published',
    ]);
    $verificationCourse->forceFill(['status' => 'published'])->saveQuietly();

    $originEnrollment = CourseEnrollment::enroll($user, $originCourse);
    app(LanguageVerificationGate::class)->resolveBlockedEnrollment($originEnrollment->fresh());

    $verificationEnrollment = CourseEnrollment::query()
        ->where('user_id', $user->getKey())
        ->where('course_id', $verificationCourse->getKey())
        ->firstOrFail();

    CourseEnrollment::withoutEvents(function () use ($verificationEnrollment): void {
        $verificationEnrollment->update([
            'status' => CourseEnrollment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    });

    app(LanguageVerificationGate::class)->syncVerifiedLanguageLevelFromEnrollment($verificationEnrollment->fresh());

    expect((int) $user->fresh()->verified_language_level_id)->toBe((int) $a2->getKey());

    $this->get(route('user.courses.show', $verificationCourse))
        ->assertRedirect(route('user.courses.show', $originCourse));
});

it('allows access to language verification courses regardless of verified language level', function () {
    $user = User::factory()->asUser()->create([
        'needs_language_level_verification' => true,
    ]);
    $this->actingAs($user);

    $a1 = LanguageLevel::query()->where('name', 'a1')->firstOrFail();
    $a2 = LanguageLevel::query()->where('name', 'a2')->firstOrFail();

    $user->update([
        'declared_language_level_id' => $a1->getKey(),
        'verified_language_level_id' => null,
        'needs_language_level_verification' => true,
    ]);

    $verificationCourse = Course::factory()->create([
        'title' => 'Verifica accessibile',
        'required_language_level_id' => $a2->getKey(),
        'is_language_verification_course' => true,
        'grants_language_level_id' => $a2->getKey(),
    ]);

    Module::factory()->create([
        'belongsTo' => (string) $verificationCourse->getKey(),
        'status' => 'published',
    ]);

    $verificationCourse->forceFill(['status' => 'published'])->saveQuietly();

    CourseEnrollment::enroll($user, $verificationCourse);

    $this->get(route('user.courses.show', $verificationCourse))
        ->assertOk()
        ->assertSee('Verifica accessibile')
        ->assertDontSee('Vai al corso di verifica');
});

it('sets language verification flag from immigrant field when creating a user', function () {
    config(['app.use_immigrant_functions' => true]);

    $declaredLevel = LanguageLevel::query()->where('name', 'a1')->firstOrFail();

    $createdUser = User::factory()->asUser()->create([
        'email' => 'worker@example.test',
        'is_foreigner_or_immigrant' => true,
        'declared_language_level_id' => $declaredLevel->getKey(),
    ]);

    expect($createdUser->needs_language_level_verification)->toBeTrue();
    expect((int) $createdUser->declared_language_level_id)->toBe((int) $declaredLevel->getKey());
});

it('forces language verification flag to false when immigrant functions are disabled', function () {
    config(['app.use_immigrant_functions' => false]);

    $createdUser = User::factory()->asUser()->create([
        'email' => 'worker-disabled@example.test',
        'is_foreigner_or_immigrant' => true,
    ]);

    expect($createdUser->needs_language_level_verification)->toBeFalse();
});
