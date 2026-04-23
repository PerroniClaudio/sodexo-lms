<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinalizeQuizSubmissionRequest;
use App\Http\Requests\StoreQuizSubmissionRequest;
use App\Jobs\ProcessQuizSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleQuizSubmission;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ModuleQuizSubmissionController extends Controller
{
    public function index(Course $course, Module $module): View
    {
        $this->abortUnlessLearningQuizModule($course, $module);

        return view('admin.module.quiz-submissions.index', [
            'course' => $course,
            'module' => $module,
            'submissions' => $module->quizSubmissions()
                ->with(['user', 'uploadedBy'])
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Course $course, Module $module, ModuleQuizSubmission $submission): View
    {
        $this->abortUnlessValidSubmission($course, $module, $submission);

        return view('admin.module.quiz-submissions.show', [
            'course' => $course,
            'module' => $module,
            'submission' => $submission->load(['answers.question', 'answers.answer', 'user', 'uploadedBy']),
        ]);
    }

    public function review(Course $course, Module $module, ModuleQuizSubmission $submission): View
    {
        $this->abortUnlessValidSubmission($course, $module, $submission);

        $module->load([
            'quizQuestions' => fn ($query) => $query->orderBy('id')->with([
                'answers' => fn ($answerQuery) => $answerQuery->orderBy('id'),
            ]),
        ]);

        return view('admin.module.quiz-submissions.review', [
            'course' => $course,
            'module' => $module,
            'submission' => $submission->load(['answers', 'user']),
        ]);
    }

    public function store(StoreQuizSubmissionRequest $request, Course $course, Module $module): RedirectResponse
    {
        $this->abortUnlessLearningQuizModule($course, $module);

        $storedPath = $request->file('submission')->store(sprintf('quiz-submissions/%d', $module->getKey()), 'local');

        $submission = $module->quizSubmissions()->create([
            'uploaded_by' => (int) $request->user()->getAuthIdentifier(),
            'disk' => 'local',
            'path' => $storedPath,
            'status' => ModuleQuizSubmission::STATUS_UPLOADED,
            'provider' => 'google_document_ai',
        ]);

        ProcessQuizSubmission::dispatch($submission);

        return redirect()
            ->route('admin.courses.modules.edit', [$course, $module])
            ->with('status', __('PDF quiz caricato e inviato in elaborazione.'));
    }

    public function finalize(
        FinalizeQuizSubmissionRequest $request,
        Course $course,
        Module $module,
        ModuleQuizSubmission $submission,
    ): RedirectResponse {
        $this->abortUnlessValidSubmission($course, $module, $submission);

        $module->load([
            'quizQuestions' => fn ($query) => $query->orderBy('id')->with([
                'answers' => fn ($answerQuery) => $answerQuery->orderBy('id'),
            ]),
        ]);

        $courseEnrollment = CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->where('user_id', $submission->user_id)
            ->whereNull('deleted_at')
            ->first();

        if ($courseEnrollment === null) {
            return back()->withErrors([
                'submission' => __('Nessuna iscrizione attiva trovata per questo utente.'),
            ]);
        }

        /** @var ModuleProgress|null $progress */
        $progress = $courseEnrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->first();

        if ($progress === null) {
            return back()->withErrors([
                'submission' => __('Nessun progresso quiz trovato per questo modulo.'),
            ]);
        }

        $answersByQuestionId = collect($request->validated('answers'))
            ->mapWithKeys(fn (array $answer): array => [
                (int) $answer['question_id'] => (string) $answer['selected_option_key'],
            ]);

        $score = 0;
        $totalScore = 0;

        DB::transaction(function () use (
            $answersByQuestionId,
            $module,
            $progress,
            $request,
            &$score,
            $submission,
            &$totalScore
        ): void {
            $submission->answers()->delete();

            foreach ($module->quizQuestions as $index => $question) {
                $totalScore += (int) $question->points;
                $selectedOptionKey = $answersByQuestionId->get($question->getKey());
                $selectedAnswer = $question->answers->values()->get($this->optionKeyIndex($selectedOptionKey));

                if ($selectedAnswer !== null && (int) $question->correct_answer_id === (int) $selectedAnswer->getKey()) {
                    $score += (int) $question->points;
                }

                $submission->answers()->create([
                    'module_quiz_question_id' => $question->getKey(),
                    'module_quiz_answer_id' => $selectedAnswer?->getKey(),
                    'question_number' => $index + 1,
                    'selected_option_key' => $selectedOptionKey,
                ]);
            }

            $submission->forceFill([
                'status' => ModuleQuizSubmission::STATUS_FINALIZED,
                'finalized_at' => now(),
                'finalized_by' => (int) $request->user()->getAuthIdentifier(),
                'score' => $score,
                'total_score' => $totalScore,
            ])->save();

            try {
                $progress->recordQuizAttempt($score, $totalScore);
            } catch (DomainException $exception) {
                throw $exception;
            }
        });

        return redirect()
            ->route('admin.courses.modules.quiz.submissions.show', [$course, $module, $submission])
            ->with('status', __('Submission finalizzata con successo.'));
    }

    private function abortUnlessLearningQuizModule(Course $course, Module $module): void
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->type === 'learning_quiz', 404);
    }

    private function abortUnlessValidSubmission(Course $course, Module $module, ModuleQuizSubmission $submission): void
    {
        $this->abortUnlessLearningQuizModule($course, $module);
        abort_unless($submission->module_id === $module->getKey(), 404);
    }

    private function optionKeyIndex(string $selectedOptionKey): int
    {
        return match ($selectedOptionKey) {
            'A' => 0,
            'B' => 1,
            'C' => 2,
            'D' => 3,
            default => 0,
        };
    }
}
