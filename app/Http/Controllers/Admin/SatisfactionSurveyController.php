<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\SatisfactionSurveyQuestion;
use App\Models\SatisfactionSurveyTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SatisfactionSurveyController extends Controller
{
    public function edit(): View
    {
        return view('admin.satisfaction-survey.edit', [
            'courseTypeLabels' => Course::availableTypeLabels(),
            'inputTypes' => SatisfactionSurveyQuestion::inputTypeOptions(),
            'indexUrl' => route('admin.api.satisfaction-survey.questions.index'),
            'storeUrl' => route('admin.api.satisfaction-survey.questions.store'),
            'reorderUrl' => route('admin.api.satisfaction-survey.questions.reorder'),
        ]);
    }

    public function indexApi(): JsonResponse
    {
        $template = $this->resolveActiveTemplate();

        return response()->json([
            'data' => $template->questions->map(fn (SatisfactionSurveyQuestion $question): array => $this->serializeQuestion($question))->values(),
        ]);
    }

    public function storeApi(Request $request): JsonResponse
    {
        $template = $this->resolveActiveTemplate();
        $validated = $this->validateQuestion($request);

        $question = DB::transaction(function () use ($template, $validated): SatisfactionSurveyQuestion {
            $question = $template->questions()->create([
                'sort_order' => ((int) $template->questions()->max('sort_order')) + 1,
                'text' => $validated['text'],
                'input_type' => $validated['input_type'],
                'excluded_course_types' => $validated['excluded_course_types'] ?? [],
            ]);

            $this->syncQuestionAnswers($question, $validated);
            $this->rebalanceQuestionSortOrder($template);

            return $question->fresh(['answers']);
        });

        return response()->json([
            'message' => __('Domanda creata con successo.'),
            'question' => $this->serializeQuestion($question),
            'questions' => $this->serializeQuestions($template->fresh('questions.answers')),
        ], 201);
    }

    public function updateApi(Request $request, SatisfactionSurveyQuestion $question): JsonResponse
    {
        $template = $this->resolveActiveTemplate();
        abort_unless($question->satisfaction_survey_template_id === $template->getKey(), 404);

        $validated = $this->validateQuestion($request);

        DB::transaction(function () use ($question, $validated, $template): void {
            $question->update([
                'text' => $validated['text'],
                'input_type' => $validated['input_type'],
                'excluded_course_types' => $validated['excluded_course_types'] ?? [],
            ]);

            $this->syncQuestionAnswers($question, $validated);
            $this->rebalanceQuestionSortOrder($template);
        });

        return response()->json([
            'message' => __('Domanda aggiornata con successo.'),
            'questions' => $this->serializeQuestions($template->fresh('questions.answers')),
        ]);
    }

    public function destroyApi(SatisfactionSurveyQuestion $question): JsonResponse
    {
        $template = $this->resolveActiveTemplate();
        abort_unless($question->satisfaction_survey_template_id === $template->getKey(), 404);

        DB::transaction(function () use ($question, $template): void {
            $question->delete();
            $this->rebalanceQuestionSortOrder($template);
        });

        return response()->json([
            'message' => __('Domanda eliminata con successo.'),
            'questions' => $this->serializeQuestions($template->fresh('questions.answers')),
        ]);
    }

    public function reorderApi(Request $request): JsonResponse
    {
        $template = $this->resolveActiveTemplate();

        $validated = $request->validate([
            'question_ids' => ['required', 'array', 'min:1'],
            'question_ids.*' => ['required', 'integer'],
        ]);

        $questions = $template->questions->keyBy('id');
        $requestedIds = collect($validated['question_ids'])->map(fn (mixed $id): int => (int) $id);

        if ($requestedIds->sort()->values()->all() !== $questions->keys()->sort()->values()->all()) {
            return response()->json([
                'message' => __('L\'ordinamento inviato non è valido.'),
            ], 422);
        }

        DB::transaction(function () use ($requestedIds, $questions): void {
            $normalizedQuestions = $this->normalizeQuestionOrder(
                $requestedIds->map(fn (int $id): SatisfactionSurveyQuestion => $questions->get($id))
            );

            $normalizedQuestions->values()->each(function (SatisfactionSurveyQuestion $question, int $index): void {
                $question->forceFill(['sort_order' => $index + 1])->save();
            });
        });

        return response()->json([
            'message' => __('Ordine aggiornato con successo.'),
            'questions' => $this->serializeQuestions($template->fresh('questions.answers')),
        ]);
    }

    private function resolveActiveTemplate(): SatisfactionSurveyTemplate
    {
        $activeTemplate = SatisfactionSurveyTemplate::active();

        if ($activeTemplate instanceof SatisfactionSurveyTemplate) {
            return $activeTemplate;
        }

        return SatisfactionSurveyTemplate::query()->create([
            'is_active' => true,
            'activated_at' => now(),
        ])->fresh(['questions.answers']);
    }

    /**
     * @return array{
     *     text: string,
     *     input_type: string,
     *     excluded_course_types?: array<int, string>,
     *     answers?: array<int, string>
     * }
     */
    private function validateQuestion(Request $request): array
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'input_type' => ['required', 'string', Rule::in(SatisfactionSurveyQuestion::inputTypeOptions())],
            'excluded_course_types' => ['nullable', 'array'],
            'excluded_course_types.*' => ['string', Rule::in(Course::availableTypes())],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string'],
        ]);

        if ($validated['input_type'] === SatisfactionSurveyQuestion::INPUT_TYPE_RADIO) {
            $answers = collect($validated['answers'] ?? [])
                ->map(fn (mixed $answer): string => trim((string) $answer))
                ->values();

            if ($answers->count() !== 5 || $answers->contains(fn (string $answer): bool => $answer === '')) {
                throw ValidationException::withMessages([
                    'answers' => __('Le domande a risposta multipla devono avere esattamente 5 risposte non vuote.'),
                ]);
            }

            $validated['answers'] = $answers->all();
        } else {
            $validated['answers'] = [];
        }

        return $validated;
    }

    private function syncQuestionAnswers(SatisfactionSurveyQuestion $question, array $validated): void
    {
        if ($question->usesTextarea()) {
            return;
        }

        $existingAnswers = $question->answers()->orderBy('sort_order')->orderBy('id')->get()->values();

        collect($validated['answers'])->each(function (string $answerText, int $index) use ($question, $existingAnswers): void {
            $answer = $existingAnswers->get($index);

            if ($answer === null) {
                $question->answers()->create([
                    'sort_order' => $index + 1,
                    'text' => $answerText,
                ]);

                return;
            }

            $answer->update([
                'sort_order' => $index + 1,
                'text' => $answerText,
            ]);
        });
    }

    private function rebalanceQuestionSortOrder(SatisfactionSurveyTemplate $template): void
    {
        $this->normalizeQuestionOrder(
            $template->questions()->with('answers')->get()
        )->values()->each(function (SatisfactionSurveyQuestion $question, int $index): void {
            if ($question->sort_order === $index + 1) {
                return;
            }

            $question->forceFill(['sort_order' => $index + 1])->save();
        });
    }

    /**
     * @param  Collection<int, SatisfactionSurveyQuestion>  $questions
     * @return Collection<int, SatisfactionSurveyQuestion>
     */
    private function normalizeQuestionOrder(Collection $questions): Collection
    {
        $groups = $questions->partition(
            fn (SatisfactionSurveyQuestion $question): bool => $question->usesRadio()
        );

        return $groups->get(0, collect())->values()->concat(
            $groups->get(1, collect())->values()
        );
    }

    private function serializeQuestions(SatisfactionSurveyTemplate $template): array
    {
        return $template->questions->map(fn (SatisfactionSurveyQuestion $question): array => $this->serializeQuestion($question))->values()->all();
    }

    private function serializeQuestion(SatisfactionSurveyQuestion $question): array
    {
        return [
            'id' => $question->getKey(),
            'text' => $question->text,
            'input_type' => $question->input_type,
            'sort_order' => $question->sort_order,
            'excluded_course_types' => $question->excluded_course_types ?? [],
            'answers' => $question->answers->map(fn ($answer): array => [
                'id' => $answer->getKey(),
                'text' => $answer->text,
                'sort_order' => $answer->sort_order,
            ])->values(),
        ];
    }
}
