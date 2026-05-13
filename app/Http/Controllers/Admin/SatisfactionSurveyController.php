<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SatisfactionSurveyTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SatisfactionSurveyController extends Controller
{
    public function edit(): View
    {
        $activeTemplate = SatisfactionSurveyTemplate::active();

        return view('admin.satisfaction-survey.edit', [
            'activeTemplate' => $activeTemplate,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.text' => ['required', 'string'],
            'questions.*.answers' => ['required', 'array', 'min:2'],
            'questions.*.answers.*' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            SatisfactionSurveyTemplate::query()->update(['is_active' => false]);

            $template = SatisfactionSurveyTemplate::query()->create([
                'is_active' => true,
                'created_by' => $request->user()?->getKey(),
                'activated_at' => now(),
            ]);

            foreach ($validated['questions'] as $questionIndex => $questionData) {
                $question = $template->questions()->create([
                    'sort_order' => $questionIndex + 1,
                    'text' => $questionData['text'],
                ]);

                foreach ($questionData['answers'] as $answerIndex => $answerText) {
                    $question->answers()->create([
                        'sort_order' => $answerIndex + 1,
                        'text' => $answerText,
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.satisfaction-survey.edit')
            ->with('status', __('Questionario di gradimento aggiornato con successo.'));
    }
}
