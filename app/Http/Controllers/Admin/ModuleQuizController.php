<?php

namespace App\Http\Controllers\Admin;

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
    public function storeQuestion(Request $request, Course $course, Module $module): RedirectResponse
    {
        $data = $request->validate([
            'text' => 'required|string',
            'points' => 'required|integer|min:1',
        ]);
        $data['module_id'] = $module->id;
        ModuleQuizQuestion::create($data);

        return back()->with('status', __('Domanda aggiunta con successo.'));
    }

    public function updateQuestion(Request $request, Course $course, Module $module, ModuleQuizQuestion $question): RedirectResponse
    {
        $data = $request->validate([
            'text' => 'required|string',
            'points' => 'required|integer|min:1',
        ]);
        $question->update($data);

        return back()->with('status', __('Domanda aggiornata.'));
    }

    public function deleteQuestion(Course $course, Module $module, ModuleQuizQuestion $question): RedirectResponse
    {
        $question->delete();

        return back()->with('status', __('Domanda eliminata.'));
    }

    public function storeAnswer(Request $request, Course $course, Module $module, ModuleQuizQuestion $question): RedirectResponse
    {
        $data = $request->validate([
            'text' => 'required|string',
        ]);
        $answer = $question->answers()->create($data);

        return back()->with('status', __('Risposta aggiunta con successo.'));
    }

    public function updateAnswer(Request $request, Course $course, Module $module, ModuleQuizQuestion $question, ModuleQuizAnswer $answer): RedirectResponse
    {
        $data = $request->validate([
            'text' => 'required|string',
        ]);
        $answer->update($data);

        return back()->with('status', __('Risposta aggiornata.'));
    }

    public function setCorrectAnswer(Request $request, Course $course, Module $module, ModuleQuizQuestion $question, ModuleQuizAnswer $answer): RedirectResponse
    {
        // Imposta la risposta corretta per la domanda
        $question->correct_answer_id = $question->correct_answer_id === $answer->id ? null : $answer->id;
        $question->save();

        return back()->with('status', __('Risposta corretta aggiornata.'));
    }

    public function deleteAnswer(Course $course, Module $module, ModuleQuizQuestion $question, ModuleQuizAnswer $answer): RedirectResponse
    {
        $answer->delete();

        return back()->with('status', __('Risposta eliminata.'));
    }

    public function downloadPdf(Course $course, Module $module)
    {
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

    private function downloadFileName(Course $course, Module $module): string
    {
        $courseSlug = Str::slug($course->title) ?: 'course';
        $moduleSlug = Str::slug($module->title) ?: 'learning-quiz';

        return "{$courseSlug}-{$moduleSlug}-quiz.pdf";
    }
}
