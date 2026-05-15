<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-6">
        <div>
            <h2 class="card-title">{{ __('Iscritti al modulo') }}</h2>
            <p class="text-sm text-base-content/70">
                {{ __('Lista degli utenti iscritti al corso e il loro stato in questo modulo.') }}
            </p>
        </div>

        @if ($moduleEnrollments->isEmpty())
            <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70">
                {{ __('Nessun iscritto presente per questo corso.') }}
            </div>
        @else
            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Utente') }}</th>
                            <th>{{ __('Stato') }}</th>
                            @if ($module->type === 'learning_quiz')
                                <th>{{ __('Tentativi') }}</th>
                                <th>{{ __('Miglior punteggio') }}</th>
                                <th>{{ __('Azioni') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($moduleEnrollments as $enrollment)
                            <tr>
                                <td>
                                    @if ($enrollment->user)
                                        {{ $enrollment->user->name }} {{ $enrollment->user->surname }}
                                    @else
                                        <span class="text-base-content/50">{{ __('Utente non disponibile') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-outline">
                                        {{ $moduleProgressStatusLabels[$enrollment->status] ?? $enrollment->status }}
                                    </span>
                                </td>
                                @if ($module->type === 'learning_quiz')
                                    <td>
                                        {{ $enrollment->quiz_attempts }} / {{ $module->max_attempts ?? '∞' }}
                                    </td>
                                    <td>
                                        @if ($enrollment->quiz_score !== null)
                                            {{ $enrollment->quiz_score }} / {{ $enrollment->quiz_total_score ?? $module->max_score }}
                                            @if ($enrollment->passed)
                                                <span class="badge badge-xs badge-success ml-2">{{ __('Superato') }}</span>
                                            @endif
                                        @else
                                            <span class="text-base-content/50">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($enrollment->quiz_attempts > 0)
                                            <a 
                                                href="{{ route('admin.courses.modules.quiz.submissions.index', [$course, $module]) }}" 
                                                class="btn btn-xs btn-outline"
                                            >
                                                {{ __('Vedi tentativi') }}
                                            </a>
                                        @else
                                            <span class="text-xs text-base-content/50">{{ __('Nessun tentativo') }}</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="text-sm text-base-content/70">
                {{ __('Totale iscritti: :count', ['count' => $moduleEnrollments->count()]) }}
            </div>
        @endif
    </div>
</div>
