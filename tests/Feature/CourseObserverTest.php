<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\User;
use App\Models\Video;

/**
 * Test suite for Course Observer Business Rules
 */
describe('CourseObserver', function () {
    it('prevents publishing course with invalid modules', function () {
        $course = Course::factory()->create(['status' => 'draft']);

        // Create invalid module
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => null, // Invalid!
            'status' => 'draft',
        ]);

        expect(fn () => $course->update(['status' => 'published']))
            ->toThrow(RuntimeException::class, 'Non è possibile pubblicare il corso perché');
    });

    it('prevents publishing course with unpublished modules', function () {
        $course = Course::factory()->create(['status' => 'draft']);

        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        // Create valid but unpublished module
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Valid Module',
            'status' => 'draft', // Not published!
        ]);

        expect(fn () => $course->update(['status' => 'published']))
            ->toThrow(RuntimeException::class);
    });

    it('allows publishing course with all valid and published modules', function () {
        $course = Course::factory()->create(['status' => 'draft']);

        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Valid Module',
            'status' => 'published',
        ]);

        $course->update(['status' => 'published']);

        expect($course->status)->toBe('published');
    });

    it('prevents editing course data when published', function () {
        $course = Course::factory()->create(['status' => 'published']);

        expect(fn () => $course->update(['title' => 'New Title']))
            ->toThrow(RuntimeException::class, 'Non è possibile modificare i dati del corso quando è pubblicato');
    });

    it('allows changing status of published course back to draft', function () {
        $course = Course::factory()->create(['status' => 'published']);

        // No enrollments, so can unpublish
        $course->update(['status' => 'draft']);

        expect($course->status)->toBe('draft');
    });

    it('prevents unpublishing course with enrollments', function () {
        $course = Course::factory()->create(['status' => 'published']);

        // Create enrollment
        $user = User::factory()->create();
        CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        expect(fn () => $course->update(['status' => 'draft']))
            ->toThrow(RuntimeException::class, 'Non è possibile rimuovere la pubblicazione del corso perché ci sono iscrizioni attive');
    });

    it('prevents unpublishing course with trashed enrollments', function () {
        $course = Course::factory()->create(['status' => 'published']);

        // Create and soft delete enrollment
        $user = User::factory()->create();
        $enrollment = CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);
        $enrollment->delete(); // Soft delete

        expect(fn () => $course->update(['status' => 'draft']))
            ->toThrow(RuntimeException::class, 'Non è possibile rimuovere la pubblicazione del corso perché ci sono iscrizioni attive');
    });

    it('prevents publishing empty course', function () {
        $course = Course::factory()->create(['status' => 'draft']);

        // No modules

        expect(fn () => $course->update(['status' => 'published']))
            ->toThrow(RuntimeException::class);
    });
});
