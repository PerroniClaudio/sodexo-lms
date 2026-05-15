<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Dettaglio submission quiz')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.quiz.submissions.index', [$course, $module]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Back to submissions') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Corso: :course. Modulo: :module.', ['course' => $course->title, 'module' => $module->title]) }}
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="text-lg font-semibold">{{ __('Risposte rilevate') }}</h2>

                    @if ($submission->answers->isEmpty())
                        <p class="text-sm text-base-content/70">{{ __('Nessuna risposta disponibile.') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            @if ($submission->source_type === 'online')
                                {{-- Tabella per submission online --}}
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Ordine') }}</th>
                                            <th>{{ __('ID Domanda') }}</th>
                                            <th>{{ __('ID Risposta') }}</th>
                                            <th>{{ __('Esito') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($submission->answers->sortBy('id') as $index => $answer)
                                            @php
                                                $isCorrect = $answer->question && $answer->question->correct_answer_id === $answer->module_quiz_answer_id;
                                            @endphp
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td class="font-mono text-xs">{{ $answer->module_quiz_question_id }}</td>
                                                <td class="font-mono text-xs">{{ $answer->module_quiz_answer_id ?? '—' }}</td>
                                                <td>
                                                    @if ($answer->module_quiz_answer_id !== null)
                                                        @if ($isCorrect)
                                                            <span class="badge badge-success badge-sm">{{ __('Corretta') }}</span>
                                                        @else
                                                            <span class="badge badge-error badge-sm">{{ __('Sbagliata') }}</span>
                                                        @endif
                                                    @else
                                                        <span class="badge badge-ghost badge-sm">{{ __('Non risposta') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                {{-- Tabella per submission upload --}}
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Domanda') }}</th>
                                            <th>{{ __('Opzione') }}</th>
                                            <th>{{ __('Confidence') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($submission->answers->sortBy('question_number') as $answer)
                                            <tr>
                                                <td>{{ $answer->question_number }}</td>
                                                <td>{{ $answer->selected_option_key ?? '—' }}</td>
                                                <td>{{ $answer->confidence !== null ? number_format((float) $answer->confidence, 2) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @endif

                    @if ($submission->status === \App\Models\ModuleQuizSubmission::STATUS_NEEDS_REVIEW)
                        <div class="flex justify-end">
                            <a href="{{ route('admin.courses.modules.quiz.submissions.review', [$course, $module, $submission]) }}" class="btn btn-primary">
                                {{ __('Apri review') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="text-lg font-semibold">{{ __('Metadata') }}</h2>
                    <dl class="grid gap-2 text-sm">
                        <div>
                            <dt class="font-medium">{{ __('Modalità') }}</dt>
                            <dd>
                                @if ($submission->source_type === 'online')
                                    <span class="badge badge-primary">{{ __('Online') }}</span>
                                @elseif ($submission->source_type === 'upload')
                                    <span class="badge badge-secondary">{{ __('Upload') }}</span>
                                @else
                                    {{ $submission->source_type }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium">{{ __('Stato') }}</dt>
                            <dd>{{ __(ucfirst($submission->status)) }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium">{{ __('Utente') }}</dt>
                            <dd>{{ $submission->user?->name ? $submission->user->name.' '.$submission->user->surname : __('Non rilevato') }}</dd>
                        </div>
                        @if ($submission->source_type === 'upload')
                            <div>
                                <dt class="font-medium">{{ __('File') }}</dt>
                                <dd class="break-all">{{ $submission->path }}</dd>
                            </div>
                        @endif
                        @if ($submission->started_at && $submission->source_type === 'online')
                            <div>
                                <dt class="font-medium">{{ __('Iniziato il') }}</dt>
                                <dd>{{ $submission->started_at?->format('d/m/Y H:i') }}</dd>
                            </div>
                        @endif
                        @if ($submission->submitted_at && $submission->source_type === 'online')
                            <div>
                                <dt class="font-medium">{{ __('Completato il') }}</dt>
                                <dd>{{ $submission->submitted_at?->format('d/m/Y H:i') }}</dd>
                            </div>
                        @endif
                        @if ($submission->score !== null)
                            <div>
                                <dt class="font-medium">{{ __('Punteggio') }}</dt>
                                <dd>{{ $submission->score }} / {{ $submission->total_score }}</dd>
                            </div>
                        @endif
                        @if ($submission->error_message)
                            <div>
                                <dt class="font-medium text-error">{{ __('Errore') }}</dt>
                                <dd class="text-error">{{ $submission->error_message }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($submission->provider_payload !== null && $submission->source_type === 'upload')
                        <div class="grid gap-2">
                            <h3 class="font-medium">{{ __('Output provider') }}</h3>
                            <pre class="max-h-96 overflow-auto rounded-lg bg-base-200 p-3 text-xs">{{ json_encode($submission->provider_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card Domande e Risposte --}}
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <h2 class="text-lg font-semibold">{{ __('Domande e Risposte') }}</h2>

                @if ($submission->answers->isEmpty())
                    <p class="text-sm text-base-content/70">{{ __('Nessuna risposta disponibile.') }}</p>
                @else
                    <div class="grid gap-4">
                        @foreach ($submission->answers->sortBy($submission->source_type === 'online' ? 'id' : 'question_number') as $index => $answer)
                            <div class="rounded-lg border border-base-300 p-4">
                                <div class="mb-3 flex items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="mb-1 flex items-center gap-2">
                                            <span class="badge badge-neutral badge-sm">{{ __('Domanda') }} {{ $submission->source_type === 'online' ? ($index + 1) : $answer->question_number }}</span>
                                            @if ($submission->source_type === 'online' && $answer->question)
                                                <span class="font-mono text-xs text-base-content/50">ID: {{ $answer->module_quiz_question_id }}</span>
                                            @endif
                                        </div>
                                        <p class="text-base font-medium">
                                            @if ($submission->source_type === 'online')
                                                {{ $answer->question?->text ?? __('Domanda non trovata') }}
                                            @else
                                                {{ __('Domanda') }} {{ $answer->question_number }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-3 rounded bg-base-200 p-3">
                                    <div class="mb-1 text-xs font-medium text-base-content/70">{{ __('Risposta data') }}</div>
                                    @if ($submission->source_type === 'online')
                                        @php
                                            $isCorrect = $answer->question && $answer->question->correct_answer_id === $answer->module_quiz_answer_id;
                                        @endphp
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex-1">
                                                @if ($answer->answer)
                                                    <p class="text-sm">{{ $answer->answer->text }}</p>
                                                    <span class="font-mono text-xs text-base-content/50">ID: {{ $answer->module_quiz_answer_id }}</span>
                                                @else
                                                    <p class="text-sm text-base-content/50">{{ __('Nessuna risposta') }}</p>
                                                @endif
                                            </div>
                                            <div>
                                                @if ($answer->module_quiz_answer_id !== null)
                                                    @if ($isCorrect)
                                                        <span class="badge badge-success">{{ __('Corretta') }}</span>
                                                    @else
                                                        <span class="badge badge-error">{{ __('Sbagliata') }}</span>
                                                    @endif
                                                @else
                                                    <span class="badge badge-ghost">{{ __('Non risposta') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-4">
                                            <p class="flex-1 text-sm">
                                                {{ __('Opzione') }}: <strong>{{ $answer->selected_option_key ?? '—' }}</strong>
                                            </p>
                                            @if ($answer->confidence !== null)
                                                <span class="text-xs text-base-content/50">
                                                    {{ __('Confidence') }}: {{ number_format((float) $answer->confidence, 2) }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
