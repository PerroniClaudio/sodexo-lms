<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizQuestion;
use App\Models\ModuleQuizAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

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
}
