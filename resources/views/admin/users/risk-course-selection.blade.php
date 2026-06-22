<x-layouts.admin>
    @vite('resources/js/admin-risk-course-selection.js')
    
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Selezione manuale corso')">
            <x-slot:actions>
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost">{{ __('Torna utente') }}</a>
            </x-slot:actions>
        </x-page-header>

        <p class="text-sm text-base-content/70">{{ $user->full_name }}</p>

        @php
            $riskSummaryCardClass = match ($riskSummary['risk_badge_class'] ?? 'badge-ghost') {
                'badge-success' => 'border-success/40 bg-success/5',
                'badge-warning' => 'border-warning/40 bg-warning/5',
                'badge-error' => 'border-error/40 bg-error/5',
                default => 'border-base-300 bg-base-100',
            };
        @endphp

        {{-- Rischio attuale --}}
        <div class="card border shadow-sm {{ $riskSummaryCardClass }}">
            <div class="card-body gap-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="card-title">{{ __('Rischio attuale') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ $riskSummary['message'] }}
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="badge badge-lg {{ $riskSummary['risk_badge_class'] }} h-fit">
                            {{ $riskSummary['risk_label'] ?? __('Non applicabile') }}
                        </span>
                    </div>
                </div>

                <div class="grid gap-3">
                    @forelse ($riskSummary['risk_based_requirements'] as $riskBasedRequirement)
                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div class="space-y-1">
                                    <div class="font-semibold text-base-content">{{ $riskBasedRequirement['risk_based_requirement_name'] }}</div>
                                    @if ($riskBasedRequirement['risk_based_requirement_description'])
                                        <p class="text-sm text-base-content/70">{{ $riskBasedRequirement['risk_based_requirement_description'] }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-col items-start gap-2 md:items-end">
                                    <span class="badge {{
                                        $riskBasedRequirement['status'] === 'satisfied'
                                            ? 'badge-success badge-soft'
                                            : ($riskBasedRequirement['status'] === 'expired' ? 'badge-warning badge-soft' : 'badge-error badge-soft')
                                    }} h-fit">
                                        {{ $riskBasedRequirement['status_label'] }}
                                    </span>
                                    @if ($riskBasedRequirement['certificate_expires_at'])
                                        <p class="text-sm text-base-content/70">
                                            {{ __('Scadenza: :date', ['date' => $riskBasedRequirement['certificate_expires_at']]) }}
                                        </p>
                                    @endif
                                    @if (($riskBasedRequirement['covered_by_higher_risk_certificate'] ?? false) && ($riskBasedRequirement['covering_risk_label'] ?? null))
                                        <p class="text-sm text-base-content/70">
                                            {{ __('Coperto da attestato valido di livello superiore: :risk', ['risk' => $riskBasedRequirement['covering_risk_label']]) }}
                                        </p>
                                    @endif
                                    @if (in_array($riskBasedRequirement['status'], ['missing', 'expired'], true) && $riskBasedRequirement['required_course_validity_type_label'])
                                        <p class="text-sm text-base-content/70">
                                            {{ __('Richiesto: :type', ['type' => \Illuminate\Support\Str::lower($riskBasedRequirement['required_course_validity_type_label'])]) }}
                                        </p>
                                    @endif
                                    @if ($riskBasedRequirement['status'] === 'missing')
                                        @if (($riskBasedRequirement['has_associated_course'] ?? false) && ! empty($riskBasedRequirement['associated_course_titles'] ?? []))
                                            <p class="text-sm text-info">
                                                {{ __('Corso già associato: :courses', ['courses' => implode(', ', $riskBasedRequirement['associated_course_titles'])]) }}
                                            </p>
                                        @else
                                            <p class="text-sm text-base-content/70">
                                                {{ __('Corso associato: nessuno') }}
                                            </p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-base-content/70">{{ __('Nessun requisito di rischio disponibile.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Variazioni di rischio future --}}
        @if (! empty($riskSummary['future_risk_transitions']))
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <div class="space-y-1">
                        <h2 class="card-title">{{ __('Variazioni di rischio future') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Le mansioni con decorrenza o termine futuro cambiano il rischio nelle date seguenti.') }}
                        </p>
                    </div>

                    @foreach ($riskSummary['future_risk_transitions'] as $futureRiskTransition)
                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                            <div class="flex items-start justify-between gap-4 mb-3">
                                <div>
                                    <div class="text-sm text-base-content/70">{{ $futureRiskTransition['effective_on_label'] }}</div>
                                    <span class="mt-2 inline-flex badge {{ $futureRiskTransition['risk_badge_class'] }} h-fit">
                                        {{ $futureRiskTransition['risk_label'] }}
                                    </span>
                                </div>
                            </div>

                            @if (!empty($futureRiskTransition['risk_based_requirements']))
                                <div class="grid gap-2 mt-3">
                                    @foreach ($futureRiskTransition['risk_based_requirements'] as $requirement)
                                        <div class="rounded border border-base-300 bg-base-100 p-3">
                                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                                <div class="space-y-1">
                                                    <div class="font-medium text-sm">{{ $requirement['risk_based_requirement_name'] }}</div>
                                                    @if ($requirement['risk_based_requirement_description'])
                                                        <p class="text-xs text-base-content/70">{{ $requirement['risk_based_requirement_description'] }}</p>
                                                    @endif
                                                </div>
                                                <div class="flex flex-col items-start gap-1 md:items-end">
                                                    <span class="badge badge-sm {{
                                                        $requirement['status'] === 'satisfied'
                                                            ? 'badge-success badge-soft'
                                                            : ($requirement['status'] === 'expired' ? 'badge-warning badge-soft' : 'badge-error badge-soft')
                                                    }} h-fit">
                                                        {{ $requirement['status_label'] }}
                                                    </span>
                                                    @if ($requirement['certificate_expires_at'])
                                                        <p class="text-xs text-base-content/70">
                                                            {{ __('Scadenza: :date', ['date' => $requirement['certificate_expires_at']]) }}
                                                        </p>
                                                    @endif
                                                    @if (in_array($requirement['status'], ['missing', 'expired'], true) && $requirement['required_course_validity_type_label'])
                                                        <p class="text-xs text-base-content/70">
                                                            {{ __('Richiesto: :type', ['type' => \Illuminate\Support\Str::lower($requirement['required_course_validity_type_label'])]) }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Certificati utente --}}
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <h2 class="card-title">{{ __('Certificati utente') }}</h2>
                @if ($latestCertificates->isEmpty())
                    <p class="text-sm text-base-content/70">{{ __('Nessun certificato disponibile.') }}</p>
                @else
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($latestCertificates as $item)
                            <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                <div class="font-medium">{{ $item['requirement']->name }}</div>
                                <div class="mt-1 text-sm text-base-content/70">{{ $item['certificate']?->name ?? __('Nessun certificato') }}</div>
                                @if ($item['certificate'])
                                    <div class="mt-2 text-sm text-base-content/70">
                                        {{ __('Conseguito: :issued', ['issued' => $item['issued_at_label'] ?? '—']) }}
                                        <br>
                                        {{ __('Scadenza: :expires', ['expires' => $item['expires_at_label'] ?? '—']) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Corsi consigliati --}}
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Corsi consigliati per assegnazione') }}</h2>
                    <p class="text-sm text-base-content/70">{{ __('Sono mostrati i corsi pubblicati che corrispondono ai requisiti mancanti o scaduti del rischio corrente e delle variazioni di rischio future dell\'utente.') }}</p>
                </div>

                {{-- Barra di ricerca --}}
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <label class="input input-bordered flex w-full max-w-md items-center gap-2">
                        <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                        <input 
                            type="search" 
                            class="grow" 
                            placeholder="{{ __('Cerca corsi per titolo, tipo, requisiti...') }}" 
                            data-courses-search-input
                        >
                    </label>
                    <button type="button" class="btn btn-primary btn-outline" data-courses-search-button>
                        {{ __('Cerca') }}
                    </button>
                </div>

                {{-- Loading state --}}
                <div class="hidden rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/70" data-courses-loading>
                    {{ __('Caricamento corsi in corso...') }}
                </div>

                {{-- Tabella corsi --}}
                <div 
                    id="recommended-courses-table"
                    data-api-url="{{ route('admin.api.users.recommended-courses', $user) }}"
                    data-enroll-url="{{ route('admin.users.risk-course-selection.enroll', $user) }}"
                    data-user-id="{{ $user->id }}"
                    data-csrf-token="{{ csrf_token() }}"
                >
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>
                                        <a href="#" class="inline-flex items-center gap-2" data-sort-column="title">
                                            <span>{{ __('Corso') }}</span>
                                            <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="title" />
                                        </a>
                                    </th>
                                    <th>
                                        <a href="#" class="inline-flex items-center gap-2" data-sort-column="course_type">
                                            <span>{{ __('Tipo corso') }}</span>
                                            <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="course_type" />
                                        </a>
                                    </th>
                                    <th>{{ __('Requisiti coperti') }}</th>
                                    <th>
                                        <a href="#" class="inline-flex items-center gap-2" data-sort-column="validity_type">
                                            <span>{{ __('Tipo conseguimento') }}</span>
                                            <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="validity_type" />
                                        </a>
                                    </th>
                                    <th>{{ __('Prerequisiti') }}</th>
                                    <th>{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody data-courses-table-body>
                                {{-- Popolato dinamicamente via JavaScript --}}
                            </tbody>
                        </table>
                    </div>

                    {{-- Empty state --}}
                    <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-sm text-base-content/70" data-courses-empty>
                        {{ __('Nessun corso compatibile trovato per i requisiti correnti o futuri.') }}
                    </div>

                    {{-- Paginazione --}}
                    <div class="mt-4 flex flex-col items-center gap-4 sm:flex-row sm:justify-between" data-courses-pagination>
                        <div class="text-sm text-base-content/70" data-pagination-info></div>
                        <div class="flex gap-2" data-pagination-buttons></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal conferma iscrizione --}}
    <dialog id="enroll-confirmation-modal" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg mb-4">{{ __('Conferma iscrizione') }}</h3>
            <p class="mb-4" data-modal-course-title></p>
            <p class="text-sm text-base-content/70 mb-4">{{ __('Sei sicuro di voler iscrivere l\'utente a questo corso?') }}</p>
            <div class="flex gap-2 justify-end">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('enroll-confirmation-modal').close()">
                    {{ __('Annulla') }}
                </button>
                <form method="POST" data-enroll-form>
                    @csrf
                    <input type="hidden" name="course_id" data-modal-course-id>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Conferma iscrizione') }}
                    </button>
                </form>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Close') }}</button>
        </form>
    </dialog>
</x-layouts.admin>
