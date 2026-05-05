<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizModuleController extends Controller
{
    /**
     * Return the quiz questions and answers (without revealing correct answer).
     */
    public function show(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless($module->isQuiz(), 404);

        $this->resolveEnrollment($course);

        $questions = $module->quizQuestions()
            ->with('answers')
            ->orderBy('id')
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'text' => $q->text,
                'points' => $q->points,
                'answers' => $q->answers->map(fn ($a) => [
                    'id' => $a->id,
                    'text' => $a->text,
                ]),
            ]);

        return response()->json([
            'passing_score' => $module->passing_score,
            'max_score' => $module->max_score,
            'questions' => $questions,
        ]);
    }

    /**
     * Submit quiz answers and record the attempt.
     */
    public function submit(Request $request, Course $course, Module $module): JsonResponse
    {
        abort_unless($module->isQuiz(), 404);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer'],
        ]);

        $enrollment = $this->resolveEnrollment($course);
        $progress = $this->resolveProgress($enrollment, $module);

        $questions = $module->quizQuestions()->with('answers')->get();
        $score = 0;

        foreach ($questions as $question) {
            $selectedAnswerId = $validated['answers'][$question->id] ?? null;
            if ($selectedAnswerId !== null && (int) $selectedAnswerId === (int) $question->correct_answer_id) {
                $score += $question->points;
            }
        }

        $totalScore = $module->max_score ?? $questions->sum('points');

        try {
            $progress->recordQuizAttempt($score, $totalScore);
        } catch (DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $passed = $score >= ($module->passing_score ?? 0);

        return response()->json([
            'success' => true,
            'score' => $score,
            'total_score' => $totalScore,
            'passing_score' => $module->passing_score,
            'passed' => $passed,
        ]);
    }

    private function resolveEnrollment(Course $course): CourseEnrollment
    {
        return CourseEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('course_id', $course->getKey())
            ->firstOrFail();
    }

    private function resolveProgress(CourseEnrollment $enrollment, Module $module): ModuleProgress
    {
        return ModuleProgress::query()
            ->where('course_user_id', $enrollment->getKey())
            ->where('module_id', $module->getKey())
            ->firstOrFail();
    }
}
