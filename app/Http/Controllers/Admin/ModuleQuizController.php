<?php

namespace App\Http\Controllers\Admin;

use App\Actions\BuildLearningQuizPdfPayload;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

class ModuleQuizController extends Controller
{
    /**
     * API: restituisce tutte le domande e risposte del quiz di un modulo
     */
    public function questionsWithAnswersApi(Course $course, Module $module)
    {
        $questions = $module->quizQuestions()->with(['answers'])->orderBy('id')->get();
        return response()->json([
            'success' => true,
            'questions' => $questions,
        ]);
    }

    public function downloadPdf(
        Course $course,
        Module $module,
    ) {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($course->type === 'res', 404);
        abort_unless($module->type === 'learning_quiz', 404);

        $module->load([
            'quizQuestions' => fn ($query) => $query
                ->orderBy('id')
                ->with([
                    'answers' => fn ($answerQuery) => $answerQuery->orderBy('id'),
                ]),
        ]);

        return Pdf::view('pdf.learning-quiz', [
            'course' => $course,
            'module' => $module,
        ])
            ->driver('dompdf')
            ->download($this->downloadFileName($course, $module));
    }

    public function downloadAnswerSheetPdf(
        Course $course,
        Module $module,
        BuildLearningQuizPdfPayload $buildLearningQuizPdfPayload,
    ) {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($course->type === 'res', 404);
        abort_unless($module->type === 'learning_quiz', 404);

        $course->load([
            'users' => fn ($query) => $query->orderBy('surname')->orderBy('name')->orderBy('users.id'),
        ]);

        return Pdf::view(
            'pdf.learning-quiz-answer-sheet',
            $buildLearningQuizPdfPayload($course, $module)
        )
            ->driver('dompdf')
            ->download($this->answerSheetDownloadFileName($course, $module));
    }

    private function downloadFileName(Course $course, Module $module): string
    {
        $courseSlug = Str::slug($course->title) ?: 'course';
        $moduleSlug = Str::slug($module->title) ?: 'learning-quiz';

        return "{$courseSlug}-{$moduleSlug}-quiz.pdf";
    }

    private function answerSheetDownloadFileName(Course $course, Module $module): string
    {
        $courseSlug = Str::slug($course->title) ?: 'course';
        $moduleSlug = Str::slug($module->title) ?: 'learning-quiz';

        return "{$courseSlug}-{$moduleSlug}-answer-sheet.pdf";
    }


    /**
     * API: aggiungi domanda quiz (risposta JSON)
     */
    public function storeQuestionApi(Request $request, Course $course, Module $module)
    {
        $data = $request->validate([
            'text' => 'required|string',
            'points' => 'required|integer|min:1',
        ]);
        $data['module_id'] = $module->id;
        $question = ModuleQuizQuestion::create($data);

        return response()->json([
            'success' => true,
            'message' => __('Domanda aggiunta con successo.'),
            'question' => $question,
        ]);
    }

    /**
     * API: aggiorna domanda quiz (risposta JSON)
     */
    public function updateQuestionApi(Request $request, Course $course, Module $module, ModuleQuizQuestion $question)
    {
        $data = $request->validate([
            'text' => 'required|string',
            'points' => 'required|integer|min:1',
        ]);
        $question->update($data);
        return response()->json([
            'success' => true,
            'message' => __('Domanda aggiornata.'),
            'question' => $question,
        ]);
    }

    /**
     * API: elimina domanda quiz (risposta JSON)
     */
    public function deleteQuestionApi(Course $course, Module $module, ModuleQuizQuestion $question)
    {
        $question->delete();
        return response()->json([
            'success' => true,
            'message' => __('Domanda eliminata.'),
        ]);
    }

    /**
     * API: aggiungi risposta (JSON)
     */
    public function storeAnswerApi(Request $request, Course $course, Module $module, ModuleQuizQuestion $question)
    {
        $data = $request->validate([
            'text' => 'required|string',
        ]);
        $answer = $question->answers()->create($data);
        return response()->json([
            'success' => true,
            'message' => __('Risposta aggiunta con successo.'),
            'answer' => $answer,
        ]);
    }

    /**
     * API: aggiorna risposta (JSON)
     */
    public function updateAnswerApi(Request $request, Course $course, Module $module, ModuleQuizQuestion $question, ModuleQuizAnswer $answer)
    {
        $data = $request->validate([
            'text' => 'required|string',
        ]);
        $answer->update($data);
        return response()->json([
            'success' => true,
            'message' => __('Risposta aggiornata.'),
            'answer' => $answer,
        ]);
    }

    /**
     * API: elimina risposta (JSON)
     */
    public function deleteAnswerApi(Course $course, Module $module, ModuleQuizQuestion $question, ModuleQuizAnswer $answer)
    {
        $answer->delete();
        return response()->json([
            'success' => true,
            'message' => __('Risposta eliminata.'),
        ]);
    }

    /**
     * API: imposta risposta corretta (JSON)
     */
    public function setCorrectAnswerApi(Request $request, Course $course, Module $module, ModuleQuizQuestion $question, ModuleQuizAnswer $answer)
    {
        $question->correct_answer_id = $question->correct_answer_id === $answer->id ? null : $answer->id;
        $question->save();
        return response()->json([
            'success' => true,
            'message' => __('Risposta corretta aggiornata.'),
            'question' => $question,
        ]);
    }
}
