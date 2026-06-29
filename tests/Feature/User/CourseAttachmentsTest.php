<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
});

it('shows course attachments in user course detail', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $user = actingAsRole('user');
    $course = Course::factory()->create([
        'cover_image_path' => 'courses/20/attachments/cover-image.jpg',
        'poster_pdf_path' => 'courses/20/attachments/poster.pdf',
    ]);

    Storage::disk('s3')->put($course->cover_image_path, 'cover-bytes');
    Storage::disk('s3')->put($course->poster_pdf_path, 'pdf-bytes');

    CourseEnrollment::enroll($user, $course);

    $this->actingAs($user)
        ->get(route('user.courses.show', $course))
        ->assertOk()
        ->assertSee(route('user.courses.cover-image.show', $course), escape: false)
        ->assertSee(route('user.courses.poster-pdf.download', $course), escape: false)
        ->assertSeeText('Scarica locandina');
});

it('downloads poster pdf for enrolled user', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $user = actingAsRole('user');
    $course = Course::factory()->create([
        'title' => 'Corso privacy',
        'poster_pdf_path' => 'courses/20/attachments/poster.pdf',
    ]);

    Storage::disk('s3')->put($course->poster_pdf_path, 'pdf-bytes');

    CourseEnrollment::enroll($user, $course);

    $this->actingAs($user)
        ->get(route('user.courses.poster-pdf.download', $course))
        ->assertOk();
});

it('does not render empty attachment actions when files are missing', function () {
    $user = actingAsRole('user');
    $course = Course::factory()->create();

    CourseEnrollment::enroll($user, $course);

    $this->actingAs($user)
        ->get(route('user.courses.show', $course))
        ->assertOk()
        ->assertDontSeeText('Scarica locandina');
});
