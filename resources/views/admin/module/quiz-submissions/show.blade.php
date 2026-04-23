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
                    <h2 class="text-lg font-semibold">{{ __('Metadata OCR') }}</h2>
                    <dl class="grid gap-2 text-sm">
                        <div>
                            <dt class="font-medium">{{ __('Stato') }}</dt>
                            <dd>{{ $submission->status }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium">{{ __('Utente') }}</dt>
                            <dd>{{ $submission->user?->name ? $submission->user->name.' '.$submission->user->surname : __('Non rilevato') }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium">{{ __('File') }}</dt>
                            <dd class="break-all">{{ $submission->path }}</dd>
                        </div>
                        @if ($submission->error_message)
                            <div>
                                <dt class="font-medium text-error">{{ __('Errore') }}</dt>
                                <dd class="text-error">{{ $submission->error_message }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if ($submission->provider_payload !== null)
                        <div class="grid gap-2">
                            <h3 class="font-medium">{{ __('Output provider') }}</h3>
                            <pre class="max-h-96 overflow-auto rounded-lg bg-base-200 p-3 text-xs">{{ json_encode($submission->provider_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
