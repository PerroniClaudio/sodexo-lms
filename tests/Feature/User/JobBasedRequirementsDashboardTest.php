<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\JobBasedRequirement;
use App\Models\UserCertificate;
use App\Services\CourseRiskRequirementService;

test('shows unmet job-based requirements and their assigned courses on the user dashboard', function () {
    $user = actingAsRole('user');
    $requirement = JobBasedRequirement::factory()->create(['name' => 'Formazione antincendio']);
    $course = Course::factory()->published()->create(['title' => 'Corso antincendio']);
    $course->jobBasedRequirements()->attach($requirement);
    $user->jobBasedRequirements()->attach($requirement, [
        'is_active' => true,
        'valid_from' => today(),
        'calculated_at' => now(),
    ]);
    CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
    ]);

    $this->get(route('user.dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Requisiti da completare')
        ->assertSeeText('Formazione antincendio')
        ->assertSeeText('Corso antincendio')
        ->assertSee(route('user.courses.show', $course), escape: false);
});

test('does not show a requirement already covered by a valid certificate', function () {
    $user = actingAsRole('user');
    $requirement = JobBasedRequirement::factory()->create(['name' => 'Formazione già acquisita']);
    $user->jobBasedRequirements()->attach($requirement, [
        'is_active' => true,
        'valid_from' => today(),
        'calculated_at' => now(),
    ]);
    $certificate = UserCertificate::factory()->withoutExpiration()->create(['user_id' => $user->getKey()]);
    $certificate->jobBasedRequirements()->attach($requirement);

    $this->get(route('user.dashboard'))
        ->assertSuccessful()
        ->assertSeeText('Non hai requisiti ruolo/mansione da completare.')
        ->assertDontSeeText('Formazione già acquisita');
});

test('completion of a linked course creates the certificate covering its job-based requirements', function () {
    $user = actingAsRole('user');
    $requirement = JobBasedRequirement::factory()->create();
    $course = Course::factory()->published()->create();
    $course->jobBasedRequirements()->attach($requirement);
    $enrollment = CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
    ]);

    app(CourseRiskRequirementService::class)->syncJobBasedCertificatesForEnrollment($enrollment);

    expect($user->userCertificates()
        ->where('internal_course_id', $course->getKey())
        ->whereHas('jobBasedRequirements', fn ($query) => $query->whereKey($requirement->getKey()))
        ->exists())->toBeTrue();
});
