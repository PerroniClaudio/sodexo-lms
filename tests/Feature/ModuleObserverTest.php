<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\User;
use App\Models\Video;

/**
 * Test suite for Module Observer Business Rules
 */
describe('ModuleObserver', function () {
    it('prevents publishing invalid module', function () {
        $module = Module::factory()->create([
            'type' => 'video',
            'video_id' => null, // Invalid!
            'status' => 'draft',
        ]);

        expect(fn () => $module->update(['status' => 'published']))
            ->toThrow(RuntimeException::class, 'Non è possibile pubblicare il modulo perché non è valido');
    });

    it('allows publishing valid module', function () {
        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        $module = Module::factory()->create([
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Valid Video',
            'status' => 'draft',
        ]);

        $module->update(['status' => 'published']);

        expect($module->status)->toBe('published');
    });

    it('prevents editing module data when published', function () {
        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        $module = Module::factory()->create([
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Published Module',
            'status' => 'published',
        ]);

        expect(fn () => $module->update(['title' => 'New Title']))
            ->toThrow(RuntimeException::class, 'Non è possibile modificare i dati del modulo quando è pubblicato');
    });

    it('allows changing status of published module back to draft', function () {
        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        $module = Module::factory()->create([
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Published Module',
            'status' => 'published',
        ]);

        $module->update(['status' => 'draft']);

        expect($module->status)->toBe('draft');
    });

    it('prevents unpublishing module with enrollments', function () {
        $course = Course::factory()->create(['status' => 'published']);

        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        $module = Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'status' => 'published',
        ]);

        // Create enrollment
        $user = User::factory()->create();
        CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);

        expect(fn () => $module->update(['status' => 'draft']))
            ->toThrow(RuntimeException::class, 'Non è possibile rimuovere la pubblicazione del modulo perché ci sono iscrizioni associate');
    });

    it('prevents unpublishing module with trashed enrollments', function () {
        $course = Course::factory()->create(['status' => 'published']);

        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        $module = Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'status' => 'published',
        ]);

        // Create and soft delete enrollment
        $user = User::factory()->create();
        $enrollment = CourseEnrollment::factory()->create([
            'course_id' => $course->id,
            'user_id' => $user->id,
        ]);
        $enrollment->delete(); // Soft delete

        expect(fn () => $module->update(['status' => 'draft']))
            ->toThrow(RuntimeException::class, 'Non è possibile rimuovere la pubblicazione del modulo perché ci sono iscrizioni associate');
    });

    it('prevents any changes to module when course is published', function () {
        $course = Course::factory()->create(['status' => 'published']);

        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        $module = Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Module in Published Course',
            'status' => 'draft',
        ]);

        expect(fn () => $module->update(['title' => 'New Title']))
            ->toThrow(RuntimeException::class, 'Non è possibile modificare il modulo perché il corso associato è pubblicato');
    });
});
