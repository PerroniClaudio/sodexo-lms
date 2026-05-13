<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\SatisfactionSurveySubmission;
use App\Models\SatisfactionSurveySubmissionAnswer;
use App\Models\SatisfactionSurveyTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SatisfactionSurveyController extends Controller
{
    public function show(Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isSatisfactionQuiz(), 404);

        if (! $this->isLastModule($course, $module)) {
            return response()->json([
                'error' => __('Il questionario di gradimento deve essere sempre l\'ultimo modulo del corso.'),
            ], 422);
        }

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);
        abort_if($progress->status === ModuleProgress::STATUS_LOCKED, 403);

        if ($progress->status === ModuleProgress::STATUS_COMPLETED) {
            return response()->json([
                'completed' => true,
                'message' => __('Hai gia completato il questionario di gradimento per questo corso.'),
            ]);
        }

        $template = SatisfactionSurveyTemplate::active();

        if ($template === null || ! $template->isUsable()) {
            return response()->json([
                'error' => __('Questionario di gradimento non configurato.'),
            ], 422);
        }

        return response()->json([
            'completed' => false,
            'template_id' => $template->getKey(),
            'questions' => $template->questions->map(fn ($question) => [
                'id' => $question->getKey(),
                'text' => $question->text,
                'answers' => $question->answers->map(fn ($answer) => [
                    'id' => $answer->getKey(),
                    'text' => $answer->text,
                ])->values(),
            ])->values(),
        ]);
    }

    public function submit(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isSatisfactionQuiz(), 404);

        if (! $this->isLastModule($course, $module)) {
            return response()->json([
                'error' => __('Il questionario di gradimento deve essere sempre l\'ultimo modulo del corso.'),
            ], 422);
        }

        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:satisfaction_survey_templates,id'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'integer'],
        ]);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);
        abort_if($progress->status === ModuleProgress::STATUS_LOCKED, 403);

        if ($progress->status === ModuleProgress::STATUS_COMPLETED) {
            return response()->json([
                'error' => __('Questionario gia completato.'),
            ], 422);
        }

        $template = SatisfactionSurveyTemplate::query()
            ->with(['questions.answers'])
            ->findOrFail($validated['template_id']);

        $answersByQuestionId = collect($validated['answers'])->mapWithKeys(
            fn (mixed $answerId, mixed $questionId): array => [(int) $questionId => (int) $answerId]
        );

        $questionIds = $template->questions->pluck('id')->map(fn (mixed $id): int => (int) $id);

        if ($answersByQuestionId->keys()->sort()->values()->all() !== $questionIds->sort()->values()->all()) {
            return response()->json([
                'error' => __('Devi rispondere a tutte le domande del questionario.'),
            ], 422);
        }

        foreach ($template->questions as $question) {
            if (! $question->answers->pluck('id')->contains($answersByQuestionId->get((int) $question->getKey()))) {
                return response()->json([
                    'error' => __('Una o piu risposte selezionate non sono valide.'),
                ], 422);
            }
        }

        DB::transaction(function () use ($template, $course, $module, $progress, $answersByQuestionId): void {
            $submission = SatisfactionSurveySubmission::query()->create([
                'satisfaction_survey_template_id' => $template->getKey(),
                'course_id' => $course->getKey(),
                'module_id' => $module->getKey(),
                'submitted_at' => now(),
            ]);

            foreach ($template->questions as $question) {
                SatisfactionSurveySubmissionAnswer::query()->create([
                    'satisfaction_survey_submission_id' => $submission->getKey(),
                    'satisfaction_survey_question_id' => $question->getKey(),
                    'satisfaction_survey_answer_id' => $answersByQuestionId->get((int) $question->getKey()),
                ]);
            }

            $progress->completeSatisfactionSurvey();
        });

        return response()->json([
            'success' => true,
            'message' => __('Grazie per aver completato il questionario di gradimento!'),
        ]);
    }

    private function resolveEnrollment(Course $course): ?CourseEnrollment
    {
        return CourseEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('course_id', $course->getKey())
            ->first();
    }

    private function resolveProgress(CourseEnrollment $enrollment, Module $module): ?ModuleProgress
    {
        return ModuleProgress::query()
            ->where('course_user_id', $enrollment->getKey())
            ->where('module_id', $module->getKey())
            ->first();
    }

    private function isLastModule(Course $course, Module $module): bool
    {
        return ! $course->modules()
            ->whereKeyNot($module->getKey())
            ->where('type', '!=', Module::TYPE_SATISFACTION_QUIZ)
            ->where('order', '>=', $module->order)
            ->exists();
    }
}
