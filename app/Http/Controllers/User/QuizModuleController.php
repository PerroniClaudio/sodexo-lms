<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleQuizSubmission;
use App\Models\ModuleQuizSubmissionAnswer;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuizModuleController extends Controller
{
    /**
     * Get the current status of the quiz module for the user.
     */
    public function getStatus(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isQuiz(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        // Ottieni tutti i tentativi completati
        $submissions = ModuleQuizSubmission::query()
            ->where('module_id', $module->id)
            ->where('course_enrollment_id', $enrollment->id)
            ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
            ->whereIn('status', [ModuleQuizSubmission::STATUS_SUBMITTED, ModuleQuizSubmission::STATUS_FINALIZED])
            ->orderBy('created_at', 'desc')
            ->get();

        // Verifica se c'è un tentativo in corso
        $activeSubmission = ModuleQuizSubmission::query()
            ->where('module_id', $module->id)
            ->where('course_enrollment_id', $enrollment->id)
            ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
            ->whereIn('status', [ModuleQuizSubmission::STATUS_STARTED, ModuleQuizSubmission::STATUS_IN_PROGRESS])
            ->first();

        $attemptsUsed = $submissions->count() + ($activeSubmission ? 1 : 0);
        $maxAttempts = $module->max_attempts ?? null;
        $canStartNewAttempt = $maxAttempts === null || $attemptsUsed < $maxAttempts;
        
        $bestScore = $submissions->max('score');
        $passed = $progress->status === ModuleProgress::STATUS_COMPLETED;

        return response()->json([
            'module' => [
                'id' => $module->id,
                'title' => $module->title,
                'passing_score' => $module->passing_score,
                'max_score' => $module->max_score,
                'max_attempts' => $maxAttempts,
            ],
            'progress' => [
                'status' => $progress->status,
                'attempts_used' => $attemptsUsed,
                'can_start_new_attempt' => $canStartNewAttempt && !$passed,
                'passed' => $passed,
                'best_score' => $bestScore,
                'quiz_score' => $progress->quiz_score,
                'quiz_total_score' => $progress->quiz_total_score,
            ],
            'active_submission' => $activeSubmission ? [
                'id' => $activeSubmission->id,
                'status' => $activeSubmission->status,
                'started_at' => $activeSubmission->started_at?->toISOString(),
                'answered_count' => $activeSubmission->answers()->count(),
                'total_questions' => $module->quizQuestions()->count(),
            ] : null,
            'past_attempts' => $submissions->map(fn ($s) => [
                'id' => $s->id,
                'score' => $s->score,
                'total_score' => $s->total_score,
                'passed' => $s->score >= $module->passing_score,
                'submitted_at' => $s->submitted_at?->toISOString(),
            ]),
        ]);
    }

    /**
     * Start a new quiz attempt.
     */
    public function startAttempt(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isQuiz(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        // Verifica se c'è già un tentativo in corso
        $activeSubmission = ModuleQuizSubmission::query()
            ->where('module_id', $module->id)
            ->where('course_enrollment_id', $enrollment->id)
            ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
            ->whereIn('status', [ModuleQuizSubmission::STATUS_STARTED, ModuleQuizSubmission::STATUS_IN_PROGRESS])
            ->first();

        if ($activeSubmission) {
            return response()->json(['error' => 'Hai già un tentativo in corso.'], 422);
        }

        // Verifica se ha ancora tentativi disponibili
        $attemptsUsed = ModuleQuizSubmission::query()
            ->where('module_id', $module->id)
            ->where('course_enrollment_id', $enrollment->id)
            ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
            ->count();

        $maxAttempts = $module->max_attempts;
        if ($maxAttempts !== null && $attemptsUsed >= $maxAttempts) {
            return response()->json(['error' => 'Hai esaurito i tentativi disponibili.'], 422);
        }

        // Verifica se ha già superato il quiz
        if ($progress->status === ModuleProgress::STATUS_COMPLETED) {
            return response()->json(['error' => 'Hai già completato questo quiz.'], 422);
        }

        // Crea una nuova submission
        $submission = DB::transaction(function () use ($module, $enrollment, $progress) {
            $submission = ModuleQuizSubmission::create([
                'module_id' => $module->id,
                'source_type' => ModuleQuizSubmission::SOURCE_ONLINE,
                'user_id' => Auth::id(),
                'course_enrollment_id' => $enrollment->id,
                'status' => ModuleQuizSubmission::STATUS_STARTED,
                'started_at' => now(),
            ]);

            // Aggiorna il progress per segnare come iniziato
            if ($progress->status === ModuleProgress::STATUS_AVAILABLE) {
                $progress->start();
            }

            return $submission;
        });

        return response()->json([
            'success' => true,
            'submission_id' => $submission->id,
            'message' => 'Quiz iniziato. Rispondi a tutte le domande.',
        ]);
    }

    /**
     * Get the next question to answer.
     */
    public function getNextQuestion(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isQuiz(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);

        // Verifica il tentativo in corso
        $submission = ModuleQuizSubmission::query()
            ->where('module_id', $module->id)
            ->where('course_enrollment_id', $enrollment->id)
            ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
            ->whereIn('status', [ModuleQuizSubmission::STATUS_STARTED, ModuleQuizSubmission::STATUS_IN_PROGRESS])
            ->first();

        if (!$submission) {
            return response()->json(['error' => 'Nessun tentativo in corso. Devi prima iniziare il quiz.'], 422);
        }

        // Ottieni le domande già risposte
        $answeredQuestionIds = $submission->answers()->pluck('module_quiz_question_id')->toArray();

        // Ottieni la prossima domanda non risposta
        $nextQuestion = $module->quizQuestions()
            ->with('answers')
            ->whereNotIn('id', $answeredQuestionIds)
            ->orderBy('id')
            ->first();

        if (!$nextQuestion) {
            // Tutte le domande sono state risposte
            return response()->json([
                'completed' => true,
                'total_questions' => $module->quizQuestions()->count(),
                'answered_questions' => count($answeredQuestionIds),
            ]);
        }

        return response()->json([
            'completed' => false,
            'submission_id' => $submission->id,
            'question_number' => count($answeredQuestionIds) + 1,
            'total_questions' => $module->quizQuestions()->count(),
            'question' => [
                'id' => $nextQuestion->id,
                'text' => $nextQuestion->text,
                'points' => $nextQuestion->points,
                'answers' => $nextQuestion->answers->map(fn ($a) => [
                    'id' => $a->id,
                    'text' => $a->text,
                ]),
            ],
        ]);
    }

    /**
     * Submit an answer to a question.
     */
    public function submitAnswer(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isQuiz(), 404);

        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:module_quiz_questions,id'],
            'answer_id' => ['required', 'integer', 'exists:module_quiz_answers,id'],
        ]);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);

        // Verifica il tentativo in corso
        $submission = ModuleQuizSubmission::query()
            ->where('module_id', $module->id)
            ->where('course_enrollment_id', $enrollment->id)
            ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
            ->whereIn('status', [ModuleQuizSubmission::STATUS_STARTED, ModuleQuizSubmission::STATUS_IN_PROGRESS])
            ->first();

        if (!$submission) {
            return response()->json(['error' => 'Nessun tentativo in corso.'], 422);
        }

        // Verifica che la domanda appartenga al modulo
        $question = $module->quizQuestions()->with('answers')->find($validated['question_id']);
        if (!$question) {
            return response()->json(['error' => 'Domanda non valida.'], 422);
        }

        // Verifica che la risposta appartenga alla domanda
        $answer = $question->answers->find($validated['answer_id']);
        if (!$answer) {
            return response()->json(['error' => 'Risposta non valida.'], 422);
        }

        // Verifica se l'utente ha già risposto a questa domanda
        $existingAnswer = $submission->answers()
            ->where('module_quiz_question_id', $validated['question_id'])
            ->first();

        if ($existingAnswer) {
            return response()->json(['error' => 'Hai già risposto a questa domanda.'], 422);
        }

        // Salva la risposta
        DB::transaction(function () use ($submission, $validated, $question) {
            $answeredCount = $submission->answers()->count();

            ModuleQuizSubmissionAnswer::create([
                'module_quiz_submission_id' => $submission->id,
                'module_quiz_question_id' => $validated['question_id'],
                'module_quiz_answer_id' => $validated['answer_id'],
                'question_number' => $answeredCount + 1,
            ]);

            // Aggiorna lo stato della submission
            if ($submission->status === ModuleQuizSubmission::STATUS_STARTED) {
                $submission->update(['status' => ModuleQuizSubmission::STATUS_IN_PROGRESS]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Risposta salvata.',
        ]);
    }

    /**
     * Complete the quiz attempt and calculate score.
     */
    public function completeAttempt(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isQuiz(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        // Verifica il tentativo in corso
        $submission = ModuleQuizSubmission::query()
            ->where('module_id', $module->id)
            ->where('course_enrollment_id', $enrollment->id)
            ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
            ->whereIn('status', [ModuleQuizSubmission::STATUS_STARTED, ModuleQuizSubmission::STATUS_IN_PROGRESS])
            ->first();

        if (!$submission) {
            return response()->json(['error' => 'Nessun tentativo in corso.'], 422);
        }

        // Verifica che tutte le domande abbiano una risposta
        $totalQuestions = $module->quizQuestions()->count();
        $answeredQuestions = $submission->answers()->count();

        if ($answeredQuestions < $totalQuestions) {
            return response()->json([
                'error' => 'Devi rispondere a tutte le domande prima di completare il quiz.',
                'answered' => $answeredQuestions,
                'total' => $totalQuestions,
            ], 422);
        }

        // Calcola il punteggio
        $score = 0;
        $questions = $module->quizQuestions()->with('answers')->get();

        foreach ($questions as $question) {
            $submittedAnswer = $submission->answers()
                ->where('module_quiz_question_id', $question->id)
                ->first();

            if ($submittedAnswer && (int) $submittedAnswer->module_quiz_answer_id === (int) $question->correct_answer_id) {
                $score += $question->points;
            }
        }

        $totalScore = $module->max_score ?? $questions->sum('points');
        $passed = $score >= ($module->passing_score ?? 0);

        // Aggiorna la submission e il progress
        DB::transaction(function () use ($submission, $score, $totalScore, $progress, $passed) {
            $submission->update([
                'status' => ModuleQuizSubmission::STATUS_SUBMITTED,
                'score' => $score,
                'total_score' => $totalScore,
                'submitted_at' => now(),
            ]);

            // Aggiorna il ModuleProgress
            try {
                $progress->recordQuizAttempt($score, $totalScore);
            } catch (DomainException $e) {
                // Già gestito
            }
        });

        return response()->json([
            'success' => true,
            'score' => $score,
            'total_score' => $totalScore,
            'passing_score' => $module->passing_score,
            'passed' => $passed,
            'message' => $passed ? 'Quiz superato!' : 'Quiz non superato. Puoi riprovare.',
        ]);
    }

    /**
     * Abandon the current attempt (for security, if user reloads page)
     */
    public function abandonAttempt(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isQuiz(), 404);

        $enrollment = $this->resolveEnrollment($course);
        abort_unless($enrollment !== null, 403);

        $progress = $this->resolveProgress($enrollment, $module);
        abort_unless($progress !== null, 404);

        // Verifica il tentativo in corso
        $submission = ModuleQuizSubmission::query()
            ->where('module_id', $module->id)
            ->where('course_enrollment_id', $enrollment->id)
            ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
            ->whereIn('status', [ModuleQuizSubmission::STATUS_STARTED, ModuleQuizSubmission::STATUS_IN_PROGRESS])
            ->first();

        if (!$submission) {
            return response()->json(['error' => 'Nessun tentativo in corso.'], 422);
        }

        // Segna il tentativo come fallito/abbandonato
        DB::transaction(function () use ($submission, $module, $progress) {
            $submission->update([
                'status' => ModuleQuizSubmission::STATUS_FAILED,
                'submitted_at' => now(),
                'error_message' => 'Tentativo abbandonato (ricaricamento pagina o navigazione).',
            ]);

            // Incrementa i tentativi nel progress
            $progress->forceFill([
                'quiz_attempts' => $progress->quiz_attempts + 1,
                'last_accessed_at' => now(),
            ])->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Tentativo abbandonato. Hai perso un tentativo.',
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
}
