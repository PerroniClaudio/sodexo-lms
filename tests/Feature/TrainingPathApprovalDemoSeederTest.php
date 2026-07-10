<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\TrainingPath;
use App\Models\User;
use App\Services\TrainingPathEnrollmentApprovalService;
use Database\Seeders\TrainingPathApprovalDemoSeeder;

it('seeds a training path with one ineligible course and published learning quizzes', function () {
    $this->seed(TrainingPathApprovalDemoSeeder::class);
    $this->seed(TrainingPathApprovalDemoSeeder::class);

    $user = User::query()->where('email', 'utente-percorso-approvazione-demo@test.com')->sole();
    $trainingPath = TrainingPath::query()->where('code', 'DEMO-PERCORSO-APPROVAZIONE')->sole();
    $courses = $trainingPath->courses()->with('modules.quizQuestions.answers')->get();
    $issues = app(TrainingPathEnrollmentApprovalService::class)->courseIssuesFor($user, $trainingPath);

    expect($trainingPath->status)->toBe('published')
        ->and($trainingPath->isVisibleTo($user))->toBeTrue()
        ->and($courses)->toHaveCount(3)
        ->and($courses->pluck('status')->unique()->all())->toBe(['published'])
        ->and($issues)->toHaveCount(1)
        ->and($issues[0]['course_id'])->toBe(Course::query()->where('code', 'DEMO-CORSO-RISERVATO')->sole()->getKey());

    $courses->each(function (Course $course): void {
        expect($course->modules)->toHaveCount(1);

        $module = $course->modules->sole();

        expect($module->type)->toBe(Module::TYPE_LEARNING_QUIZ)
            ->and($module->status)->toBe('published')
            ->and($module->quizQuestions)->toHaveCount(2);

        $module->quizQuestions->each(function ($question): void {
            expect($question->answers)->toHaveCount(4)
                ->and($question->correct_answer_id)->not->toBeNull();
        });
    });
});
