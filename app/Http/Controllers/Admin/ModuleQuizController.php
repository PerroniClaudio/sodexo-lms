<?php

namespace App\Http\Controllers\Admin;

use App\Actions\BuildLearningQuizPdfPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportModuleQuizQuestionsRequest;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Services\ModuleQuizQuestionImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModuleQuizController extends Controller
{
    public function downloadImportTemplate(Course $course, Module $module): BinaryFileResponse
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->type === Module::TYPE_LEARNING_QUIZ, 404);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Domande quiz');
        $sheet->fromArray([['Testo della domanda', 'Punti che da la domanda', 'Testo risposta corretta', 'Risposta alternativa 1', 'Risposta alternativa 2', 'Risposta alternativa 3']]);
        $sheet->fromArray([['Qual è la capitale d’Italia?', 2, 'Roma', 'Milano', 'Napoli', 'Torino']], null, 'A2');
        $temporaryFile = tempnam(sys_get_temp_dir(), 'quiz-questions-template-');

        abort_if($temporaryFile === false, 500, 'Impossibile generare il template.');
        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download($temporaryFile, 'template-import-domande-quiz.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importQuestions(ImportModuleQuizQuestionsRequest $request, Course $course, Module $module, ModuleQuizQuestionImportService $importer): RedirectResponse
    {
        $this->ensureLearningQuizIsEditable($course, $module);
        $count = $importer->import($module, $request->file('file')->getRealPath());

        return back()->with('status', trans_choice('{1} :count domanda importata.|[2,*] :count domande importate.', $count, ['count' => $count]));
    }

    /**
     * API: restituisce tutte le domande e risposte del quiz di un modulo. solo admin e superadmin (risposta JSON)
     */
    public function questionsWithAnswersApi(Course $course, Module $module)
    {
        $questions = $module->quizQuestions()->with(['answers'])->orderBy('id')->get()->makeVisible('correct_answer_id');
        $questions = $questions->map(function ($question) {
            $question->isValid = $question->isValid();

            return $question;
        });

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
        abort_unless(in_array($module->permitted_submission, ['upload', 'all']), 404);
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
        abort_unless(in_array($module->permitted_submission, ['upload', 'all']), 404);
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
        $this->ensureLearningQuizIsEditable($course, $module);

        $data = $request->validate([
            'text' => 'required|string',
            'points' => 'required|integer|min:1',
        ]);
        $data['module_id'] = $module->id;
        $question = ModuleQuizQuestion::create($data);
        $module->updateQuizMaxScore();

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
        $this->ensureLearningQuizIsEditable($course, $module);

        $data = $request->validate([
            'text' => 'required|string',
            'points' => 'required|integer|min:1',
        ]);
        $question->update($data);
        $module->updateQuizMaxScore();

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
        $this->ensureLearningQuizIsEditable($course, $module);

        $question->delete();
        $module->updateQuizMaxScore();

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
        $this->ensureLearningQuizIsEditable($course, $module);

        $data = $request->validate([
            'text' => 'required|string',
        ]);
        $answer = $question->answers()->create($data);
        $module->updateQuizMaxScore();

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
        $this->ensureLearningQuizIsEditable($course, $module);

        $data = $request->validate([
            'text' => 'required|string',
        ]);
        $answer->update($data);
        $module->updateQuizMaxScore();

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
        $this->ensureLearningQuizIsEditable($course, $module);

        $answer->delete();
        $module->updateQuizMaxScore();

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
        $this->ensureLearningQuizIsEditable($course, $module);

        $question->correct_answer_id = $question->correct_answer_id === $answer->id ? null : $answer->id;
        $question->save();
        $module->updateQuizMaxScore();

        return response()->json([
            'success' => true,
            'message' => __('Risposta corretta aggiornata.'),
            'question' => $question,
        ]);
    }

    private function ensureLearningQuizIsEditable(Course $course, Module $module): void
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->type === Module::TYPE_LEARNING_QUIZ, 404);

        try {
            $module->ensureContentIsEditable();
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }
    }
}
