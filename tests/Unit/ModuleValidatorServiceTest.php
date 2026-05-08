<?php

use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Models\Video;
use App\Services\ModuleValidation\Contracts\ModuleValidatorInterface;
use App\Services\ModuleValidation\ModuleValidatorService;

/**
 * Test suite for Module Validation Service
 */
describe('ModuleValidatorService', function () {
    it('validates video module correctly', function () {
        $video = Video::factory()->create([
            'mux_video_status' => 'ready',
        ]);

        $module = Module::factory()->create([
            'type' => 'video',
            'title' => 'Test Video Module',
            'video_id' => $video->id,
        ]);

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeTrue();
    });

    it('fails validation for video module without video', function () {
        $module = Module::factory()->create([
            'type' => 'video',
            'title' => 'Test Video Module',
            'video_id' => null,
        ]);

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeFalse();

        $errors = $validator->getValidationErrors($module);
        expect($errors)->toContain('Il modulo video deve avere un video associato.');
    });

    it('fails validation for video module with non-ready video', function () {
        $video = Video::factory()->create([
            'mux_video_status' => 'preparing',
        ]);

        $module = Module::factory()->create([
            'type' => 'video',
            'title' => 'Test Video Module',
            'video_id' => $video->id,
        ]);

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeFalse();

        $errors = $validator->getValidationErrors($module);
        expect($errors)->toContain('Il video deve essere in stato "ready" per essere valido.');
    });

    it('validates learning quiz module correctly', function () {
        $module = Module::factory()->create([
            'type' => 'learning_quiz',
            'passing_score' => 60,
            'max_score' => 100,
            'max_attempts' => 3,
        ]);

        // Create 2 valid questions worth 50 points each
        for ($i = 0; $i < 2; $i++) {
            $question = ModuleQuizQuestion::factory()->create([
                'module_id' => $module->id,
                'points' => 50,
            ]);

            // Create 4 answers
            $answers = ModuleQuizAnswer::factory()->count(4)->create([
                'question_id' => $question->id,
            ]);

            // Set first answer as correct
            $question->update(['correct_answer_id' => $answers->first()->id]);
        }

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeTrue();
    });

    it('fails validation for learning quiz without valid questions', function () {
        $module = Module::factory()->create([
            'type' => 'learning_quiz',
            'passing_score' => 60,
            'max_score' => 100,
            'max_attempts' => 3,
        ]);

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeFalse();

        $errors = $validator->getValidationErrors($module);
        expect($errors)->toContain('Il quiz deve avere almeno una domanda valida.');
    });

    it('fails validation for learning quiz with incorrect max_score', function () {
        $module = Module::factory()->create([
            'type' => 'learning_quiz',
            'passing_score' => 60,
            'max_score' => 200, // Wrong! Should be 100
            'max_attempts' => 3,
        ]);

        // Create 2 valid questions worth 50 points each (total 100)
        for ($i = 0; $i < 2; $i++) {
            $question = ModuleQuizQuestion::factory()->create([
                'module_id' => $module->id,
                'points' => 50,
            ]);

            $answers = ModuleQuizAnswer::factory()->count(4)->create([
                'question_id' => $question->id,
            ]);

            $question->update(['correct_answer_id' => $answers->first()->id]);
        }

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeFalse();
    });

    it('fails validation for learning quiz with passing_score > max_score', function () {
        $module = Module::factory()->create([
            'type' => 'learning_quiz',
            'passing_score' => 150, // Wrong! Greater than max_score
            'max_score' => 100,
            'max_attempts' => 3,
        ]);

        // Create valid questions
        for ($i = 0; $i < 2; $i++) {
            $question = ModuleQuizQuestion::factory()->create([
                'module_id' => $module->id,
                'points' => 50,
            ]);

            $answers = ModuleQuizAnswer::factory()->count(4)->create([
                'question_id' => $question->id,
            ]);

            $question->update(['correct_answer_id' => $answers->first()->id]);
        }

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeFalse();

        $errors = $validator->getValidationErrors($module);
        expect($errors)->toContain('Il punteggio di superamento (150) non può essere maggiore del punteggio massimo (100).');
    });

    it('fails validation for learning quiz with max_attempts <= 0', function () {
        $module = Module::factory()->create([
            'type' => 'learning_quiz',
            'passing_score' => 60,
            'max_score' => 100,
            'max_attempts' => 0, // Wrong! Must be > 0
        ]);

        // Create valid questions
        for ($i = 0; $i < 2; $i++) {
            $question = ModuleQuizQuestion::factory()->create([
                'module_id' => $module->id,
                'points' => 50,
            ]);

            $answers = ModuleQuizAnswer::factory()->count(4)->create([
                'question_id' => $question->id,
            ]);

            $question->update(['correct_answer_id' => $answers->first()->id]);
        }

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeFalse();
    });

    it('validates live module as always valid', function () {
        $module = Module::factory()->create([
            'type' => 'live',
        ]);

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeTrue();
    });

    it('validates satisfaction quiz module as always valid', function () {
        $module = Module::factory()->create([
            'type' => 'satisfaction_quiz',
        ]);

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeTrue();
    });

    it('validates resource module as always valid', function () {
        $module = Module::factory()->create([
            'type' => 'res',
        ]);

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeTrue();
    });

    it('validates scorm module as always valid', function () {
        $module = Module::factory()->create([
            'type' => 'scorm',
        ]);

        $validator = app(ModuleValidatorService::class);

        expect($validator->validate($module))->toBeTrue();
    });

    it('throws exception for unknown module type', function () {
        $module = Module::factory()->create([
            'type' => 'unknown_type',
        ]);

        $validator = app(ModuleValidatorService::class);

        expect(fn () => $validator->validate($module))
            ->toThrow(InvalidArgumentException::class, 'Nessun validator configurato per il tipo di modulo: unknown_type');
    });

    it('allows registering custom validators', function () {
        $validator = app(ModuleValidatorService::class);

        $customValidatorClass = new class implements ModuleValidatorInterface
        {
            private array $errors = [];

            public function validate(Module $module): bool
            {
                return true;
            }

            public function getErrors(): array
            {
                return $this->errors;
            }
        };

        $validator->registerValidator('custom_type', get_class($customValidatorClass));

        $module = Module::factory()->create([
            'type' => 'custom_type',
        ]);

        expect($validator->validate($module))->toBeTrue();
    });
});
