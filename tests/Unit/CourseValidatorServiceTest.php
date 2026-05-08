<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Models\Video;
use App\Services\CourseValidation\CourseValidatorService;

/**
 * Test suite for Course Validation Service
 */
describe('CourseValidatorService', function () {
    it('validates course with all valid modules', function () {
        $course = Course::factory()->create();

        // Create 2 valid video modules
        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        Module::factory()->count(2)->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Test Video',
        ]);

        $validator = app(CourseValidatorService::class);

        expect($validator->validate($course))->toBeTrue();
    });

    it('fails validation for course with invalid modules', function () {
        $course = Course::factory()->create();

        // Create invalid video module (no video)
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => null,
        ]);

        $validator = app(CourseValidatorService::class);

        expect($validator->validate($course))->toBeFalse();

        $errors = $validator->getValidationErrors($course);
        expect($errors)->not->toBeEmpty();
    });

    it('fails validation for course without modules', function () {
        $course = Course::factory()->create();

        $validator = app(CourseValidatorService::class);

        expect($validator->validate($course))->toBeFalse();

        $errors = $validator->getValidationErrors($course);
        expect($errors)->toContain('Il corso deve avere almeno un modulo.');
    });

    it('checks if course is publishable with all valid and published modules', function () {
        $course = Course::factory()->create();

        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        Module::factory()->count(2)->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Test Video',
            'status' => 'published',
        ]);

        $validator = app(CourseValidatorService::class);

        expect($validator->isPublishable($course))->toBeTrue();
    });

    it('fails publishability check when modules are not published', function () {
        $course = Course::factory()->create();

        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        // Create valid module but not published
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Test Video',
            'status' => 'draft', // Not published!
        ]);

        $validator = app(CourseValidatorService::class);

        expect($validator->isPublishable($course))->toBeFalse();

        $errors = $validator->getPublishabilityErrors($course);
        expect($errors)->toContain('Modulo "Test Video" (ID: '.$course->modules->first()->id.') deve essere pubblicato.');
    });

    it('fails publishability check when modules are invalid', function () {
        $course = Course::factory()->create();

        // Create invalid video module (no video) but published
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => null,
            'status' => 'published',
        ]);

        $validator = app(CourseValidatorService::class);

        expect($validator->isPublishable($course))->toBeFalse();
    });

    it('provides detailed validation errors for each module', function () {
        $course = Course::factory()->create();

        // Module 1: Invalid video
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'title' => 'Invalid Video Module',
            'video_id' => null,
        ]);

        // Module 2: Invalid quiz
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'learning_quiz',
            'title' => 'Invalid Quiz Module',
            'passing_score' => 60,
            'max_score' => 100,
            'max_attempts' => 0, // Invalid!
        ]);

        $validator = app(CourseValidatorService::class);

        $errors = $validator->getValidationErrors($course);

        expect($errors)->toContain('Modulo "Invalid Video Module" (ID: '.$course->modules->first()->id.'):');
        expect($errors)->toContain('Modulo "Invalid Quiz Module" (ID: '.$course->modules->last()->id.'):');
    });

    it('provides detailed publishability errors', function () {
        $course = Course::factory()->create();

        $video = Video::factory()->create(['mux_video_status' => 'ready']);

        // Valid module but not published
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'title' => 'Unpublished Module',
            'status' => 'draft',
        ]);

        $validator = app(CourseValidatorService::class);

        $errors = $validator->getPublishabilityErrors($course);

        expect($errors)->toContain('Modulo "Unpublished Module" (ID: '.$course->modules->first()->id.') deve essere pubblicato.');
    });

    it('validates complex course with mixed module types', function () {
        $course = Course::factory()->create();

        // Video module
        $video = Video::factory()->create(['mux_video_status' => 'ready']);
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'video',
            'video_id' => $video->id,
            'status' => 'published',
        ]);

        // Learning quiz module
        $quizModule = Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'learning_quiz',
            'passing_score' => 60,
            'max_score' => 100,
            'max_attempts' => 3,
            'status' => 'published',
        ]);

        // Create valid questions
        for ($i = 0; $i < 2; $i++) {
            $question = ModuleQuizQuestion::factory()->create([
                'module_id' => $quizModule->id,
                'points' => 50,
            ]);

            $answers = ModuleQuizAnswer::factory()->count(4)->create([
                'question_id' => $question->id,
            ]);

            $question->update(['correct_answer_id' => $answers->first()->id]);
        }

        // Live module (always valid)
        Module::factory()->create([
            'belongsTo' => $course->id,
            'type' => 'live',
            'status' => 'published',
        ]);

        $validator = app(CourseValidatorService::class);

        expect($validator->validate($course))->toBeTrue();
        expect($validator->isPublishable($course))->toBeTrue();
    });
});
