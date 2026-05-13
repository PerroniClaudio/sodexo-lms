<x-layouts.admin>
    @php
        $questions = old('questions');

        if ($questions === null) {
            $questions = $activeTemplate?->questions->map(fn ($question) => [
                'text' => $question->text,
                'answers' => $question->answers->pluck('text')->all(),
            ])->all() ?? [[
                'text' => '',
                'answers' => ['', ''],
            ]];
        }
    @endphp

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8" data-satisfaction-survey-editor>
        <x-page-header :title="__('Questionario di gradimento')">
            <x-slot:actions>
                <a href="{{ url()->previous() }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Indietro') }}</span>
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="space-y-2">
                    <h2 class="card-title">{{ __('Configurazione globale') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Tutti i questionari di gradimento dei corsi useranno queste stesse domande e risposte.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.satisfaction-survey.update') }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <div class="flex flex-col gap-4" data-questions-container>
                        @foreach ($questions as $questionIndex => $question)
                            <div class="rounded-box border border-base-300 bg-base-100 p-4" data-question-block>
                                <div class="flex items-center justify-between gap-4">
                                    <h3 class="font-semibold">{{ __('Domanda') }} {{ $questionIndex + 1 }}</h3>
                                    <button type="button" class="btn btn-ghost btn-sm" data-remove-question>
                                        {{ __('Rimuovi') }}
                                    </button>
                                </div>

                                <div class="mt-4 flex flex-col gap-4">
                                    <div class="form-control flex flex-col gap-2">
                                        <label class="label p-0">
                                            <span class="label-text font-medium">{{ __('Testo domanda') }}</span>
                                        </label>
                                        <textarea
                                            name="questions[{{ $questionIndex }}][text]"
                                            class="textarea textarea-bordered min-h-24 w-full"
                                            required
                                        >{{ $question['text'] ?? '' }}</textarea>
                                    </div>

                                    <div class="flex flex-col gap-3" data-answers-container>
                                        @foreach (($question['answers'] ?? ['', '']) as $answerIndex => $answer)
                                            <div class="flex items-center gap-3" data-answer-row>
                                                <input
                                                    type="text"
                                                    name="questions[{{ $questionIndex }}][answers][{{ $answerIndex }}]"
                                                    value="{{ $answer }}"
                                                    class="input input-bordered w-full"
                                                    required
                                                >
                                                <button type="button" class="btn btn-ghost btn-sm" data-remove-answer>
                                                    {{ __('Rimuovi') }}
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div>
                                        <button type="button" class="btn btn-outline btn-sm" data-add-answer>
                                            {{ __('Aggiungi risposta') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button type="button" class="btn btn-outline" data-add-question>
                            {{ __('Aggiungi domanda') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            {{ __('Salva questionario') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @vite('resources/js/pages/admin-satisfaction-survey-edit.js')
</x-layouts.admin>
