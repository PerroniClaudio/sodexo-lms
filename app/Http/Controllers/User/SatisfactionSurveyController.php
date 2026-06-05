<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\SatisfactionSurveyQuestion;
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
            'questions' => $template->questions
                ->reject(fn (SatisfactionSurveyQuestion $question): bool => $question->isExcludedForCourseType($course->type))
                ->values()
                ->map(fn (SatisfactionSurveyQuestion $question) => [
                    'id' => $question->getKey(),
                    'text' => $question->text,
                    'input_type' => $question->input_type,
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
            'answers.*' => ['nullable'],
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

        $applicableQuestions = $template->questions
            ->reject(fn (SatisfactionSurveyQuestion $question): bool => $question->isExcludedForCourseType($course->type))
            ->values();

        $answersByQuestionId = collect($validated['answers'])->mapWithKeys(
            fn (mixed $answerValue, mixed $questionId): array => [(int) $questionId => $answerValue]
        );

        $requiredQuestionIds = $applicableQuestions
            ->filter(fn (SatisfactionSurveyQuestion $question): bool => $question->usesRadio())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        if ($requiredQuestionIds->diff($answersByQuestionId->keys()->map(fn (mixed $id): int => (int) $id))->isNotEmpty()) {
            return response()->json([
                'error' => __('Devi rispondere a tutte le domande del questionario.'),
            ], 422);
        }

        foreach ($applicableQuestions as $question) {
            if ($question->usesTextarea()) {
                continue;
            }

            if (! $question->answers->pluck('id')->contains((int) $answersByQuestionId->get((int) $question->getKey()))) {
                return response()->json([
                    'error' => __('Una o piu risposte selezionate non sono valide.'),
                ], 422);
            }
        }

        DB::transaction(function () use ($template, $course, $module, $progress, $answersByQuestionId, $applicableQuestions): void {
            $submission = SatisfactionSurveySubmission::query()->create([
                'satisfaction_survey_template_id' => $template->getKey(),
                'course_id' => $course->getKey(),
                'module_id' => $module->getKey(),
                'submitted_at' => now(),
            ]);

            foreach ($applicableQuestions as $question) {
                $answerValue = $answersByQuestionId->get((int) $question->getKey());

                SatisfactionSurveySubmissionAnswer::query()->create([
                    'satisfaction_survey_submission_id' => $submission->getKey(),
                    'satisfaction_survey_question_id' => $question->getKey(),
                    'satisfaction_survey_answer_id' => $question->usesRadio() ? (int) $answerValue : null,
                    'open_text' => $question->usesTextarea()
                        ? filled($answerValue) ? trim((string) $answerValue) : null
                        : null,
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
