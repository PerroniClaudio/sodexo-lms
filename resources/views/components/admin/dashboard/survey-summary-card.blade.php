@props(['surveySummary'])

<div class="card border border-base-300 bg-base-100 shadow-sm" data-survey-summary-root>
    <div class="card-body gap-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="card-title">
                    <x-lucide-clipboard-check class="h-5 w-5" />
                    {{ __('Gradimento') }}
                </h2>
                <p class="text-sm text-base-content/70">{{ __('Per ogni domanda mostriamo opzione più scelta. Clic per aprire distribuzione completa.') }}</p>
            </div>
            <span class="badge badge-outline h-fit">{{ __(':count compilazioni', ['count' => $surveySummary['submissions_count']]) }}</span>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @forelse ($surveySummary['questions'] as $question)
                <div class="rounded-box border border-base-300 bg-base-100 p-4">
                    <h3 class="font-semibold">{{ $question['question'] }}</h3>

                    @if ($question['top_answer'] !== null)
                        <button
                            type="button"
                            class="mt-4 flex w-full items-center justify-between gap-4 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3 text-left transition hover:border-primary/40 hover:bg-primary/10"
                            data-survey-distribution-trigger
                            data-question="{{ $question['question'] }}"
                            data-distribution="{{ json_encode($question['distribution'], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT) }}"
                        >
                            <div>
                                <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Opzione più scelta') }}</p>
                                <p class="mt-1 font-semibold text-base-content">{{ $question['top_answer']['label'] }}</p>
                                @if ($question['has_tied_top_answers'])
                                    <p class="mt-1 text-xs text-base-content/60">{{ __('Pari merito con altre opzioni') }}</p>
                                @endif
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-lg font-semibold text-primary">{{ $question['top_answer']['percentage'] }}%</p>
                                <p class="text-xs text-base-content/60">{{ __(':count risposte', ['count' => $question['top_answer']['count']]) }}</p>
                            </div>
                        </button>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-base-300 px-4 py-3 text-sm text-base-content/60">
                            {{ __('Nessuna risposta registrata per questa domanda.') }}
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-6 text-sm text-base-content/60 xl:col-span-2">
                    {{ __('Nessun dato di gradimento disponibile.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
