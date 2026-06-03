<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Selezione manuale corso')">
            <x-slot:actions>
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost">{{ __('Torna utente') }}</a>
            </x-slot:actions>
        </x-page-header>

        @php
            $riskSummaryCardClass = match ($riskSummary['risk_badge_class'] ?? 'badge-ghost') {
                'badge-success' => 'border-success/40 bg-success/5',
                'badge-warning' => 'border-warning/40 bg-warning/5',
                'badge-error' => 'border-error/40 bg-error/5',
                default => 'border-base-300 bg-base-100',
            };
        @endphp

        <div class="grid gap-6 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)]">
            <div class="card border shadow-sm {{ $riskSummaryCardClass }}">
                <div class="card-body gap-4">
                    <h2 class="card-title">{{ $user->full_name }}</h2>
                    <div class="space-y-2 text-sm text-base-content/70">
                        <p>{{ __('Email: :value', ['value' => $user->email]) }}</p>
                        <p>{{ __('Rischio attuale:') }} <span class="badge {{ $riskSummary['risk_badge_class'] }}">{{ $riskSummary['risk_label'] ?? __('Non applicabile') }}</span></p>
                    </div>
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="card-title">{{ __('Ultimi attestati per requisito') }}</h2>
                    @if ($latestCertificates->isEmpty())
                        <p class="text-sm text-base-content/70">{{ __('Nessun attestato disponibile.') }}</p>
                    @else
                        <div class="grid gap-3">
                            @foreach ($latestCertificates as $item)
                                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                    <div class="font-medium">{{ $item['requirement']->name }}</div>
                                    <div class="mt-1 text-sm text-base-content/70">{{ $item['certificate']?->name }}</div>
                                    <div class="mt-2 text-sm text-base-content/70">
                                        {{ __('Conseguito: :issued', ['issued' => $item['issued_at_label'] ?? '—']) }}
                                        <br>
                                        {{ __('Scadenza: :expires', ['expires' => $item['expires_at_label'] ?? '—']) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (! empty($riskSummary['future_risk_transitions']))
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <div class="space-y-1">
                        <h2 class="card-title">{{ __('Variazioni di rischio future') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Considera anche le mansioni con decorrenza o termine futuro prima di scegliere il corso da assegnare.') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        @foreach ($riskSummary['future_risk_transitions'] as $futureRiskTransition)
                            <div class="rounded-box border border-base-300 bg-base-100 px-4 py-3">
                                <div class="text-sm text-base-content/70">{{ $futureRiskTransition['effective_on_label'] }}</div>
                                <span class="mt-2 inline-flex badge {{ $futureRiskTransition['risk_badge_class'] }}">
                                    {{ $futureRiskTransition['risk_label'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Corsi consigliati per assegnazione') }}</h2>
                    <p class="text-sm text-base-content/70">{{ __('Sono mostrati i corsi pubblicati che corrispondono ai requisiti mancanti o scaduti per il rischio corrente dell\'utente.') }}</p>
                </div>

                @if ($courseRecommendations->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-sm text-base-content/70">
                        {{ __('Nessun corso compatibile trovato per i requisiti correnti.') }}
                    </div>
                @else
                    <div class="grid gap-4">
                        @foreach ($courseRecommendations as $recommendation)
                            @php
                                $course = $recommendation['course'];
                                $matchingRequirement = $recommendation['matching_requirement'];
                                $requiredNeed = $recommendation['required_need'];
                            @endphp
                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="space-y-2">
                                        <div class="font-semibold text-base-content">{{ $course->title }}</div>
                                        <div class="text-sm text-base-content/70">{{ $course->description }}</div>
                                        @if ($matchingRequirement)
                                            <div class="text-sm text-base-content/70">
                                                {{ __('Requisito coperto: :requirement', ['requirement' => $matchingRequirement->name]) }}
                                            </div>
                                        @endif
                                        @if (($requiredNeed['required_course_validity_type_label'] ?? null))
                                            <span class="badge badge-outline">{{ $requiredNeed['required_course_validity_type_label'] }}</span>
                                        @endif
                                        @if (($recommendation['course_validity_type']?->value ?? null) === 'integrative' && $recommendation['integrative_start_risk_levels']->isNotEmpty())
                                            <div class="text-sm text-base-content/70">
                                                {{ __('Livelli iniziali ammessi: :levels', ['levels' => $recommendation['integrative_start_risk_levels']->map(fn ($level) => $level->label())->implode(', ')]) }}
                                            </div>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('admin.users.risk-course-selection.enroll', $user) }}" class="flex shrink-0 items-center gap-3">
                                        @csrf
                                        <input type="hidden" name="course_id" value="{{ $course->id }}">
                                        <button type="submit" class="btn btn-primary" @disabled(! $recommendation['eligible_to_enroll'])>
                                            {{ __('Assegna corso') }}
                                        </button>
                                    </form>
                                </div>

                                @if (! $recommendation['eligible_to_enroll'])
                                    <p class="mt-3 text-sm text-warning">
                                        {{ __('L\'utente non può essere iscritto perché manca un attestato valido di partenza richiesto dal corso integrativo.') }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
