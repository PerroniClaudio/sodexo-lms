@if ($module->type === 'learning_quiz')
    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Submission recenti') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Ultime :count submission ricevute per questo quiz.', ['count' => 5]) }}
                    </p>
                </div>

                <a 
                    href="{{ route('admin.courses.modules.quiz.submissions.index', [$course, $module]) }}" 
                    class="btn btn-primary"
                >
                    <span>{{ __('Vedi tutte le submission') }}</span>
                    <x-lucide-external-link class="h-4 w-4" />
                </a>
            </div>

            @if ($recentQuizSubmissions->isEmpty())
                <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70">
                    {{ __('Nessuna submission disponibile.') }}
                </div>
            @else
                <div class="overflow-x-auto rounded-box border border-base-300">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>{{ __('Utente') }}</th>
                                <th>{{ __('Modalità') }}</th>
                                <th>{{ __('Stato') }}</th>
                                <th>{{ __('Punteggio') }}</th>
                                <th>{{ __('Data') }}</th>
                                <th>{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentQuizSubmissions as $submission)
                                <tr>
                                    <td>
                                        @if ($submission->user)
                                            {{ $submission->user->name }} {{ $submission->user->surname }}
                                        @else
                                            <span class="text-base-content/50">{{ __('Utente non rilevato') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($submission->source_type === 'online')
                                            <span class="badge badge-primary badge-sm">{{ __('Online') }}</span>
                                        @elseif ($submission->source_type === 'upload')
                                            <span class="badge badge-secondary badge-sm">{{ __('Upload') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-sm">{{ $submission->source_type }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-outline badge-sm">
                                            {{ __($submission->status) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($submission->score !== null)
                                            {{ $submission->score }} / {{ $submission->total_score }}
                                            @if ($submission->score >= $module->passing_score)
                                                <span class="badge badge-xs badge-success ml-1">{{ __('Superato') }}</span>
                                            @else
                                                <span class="badge badge-xs badge-error ml-1">{{ __('Non superato') }}</span>
                                            @endif
                                        @else
                                            <span class="text-base-content/50">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($submission->submitted_at)
                                            {{ $submission->submitted_at->format('d/m/Y H:i') }}
                                        @elseif ($submission->created_at)
                                            {{ $submission->created_at->format('d/m/Y H:i') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        <a 
                                            href="{{ route('admin.courses.modules.quiz.submissions.show', [$course, $module, $submission]) }}" 
                                            class="btn btn-xs btn-outline"
                                        >
                                            {{ __('Dettaglio') }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endif
