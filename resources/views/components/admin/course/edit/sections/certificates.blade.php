@props([
    'allRiskBasedRequirementsPayload',
    'course',
    'courseRiskRequirementValidityTypeLabels',
    'courseValidator',
    'riskBasedRequirements',
    'riskLevels',
    'selectedRiskBasedRequirementsPayload',
    'updateUrl',
])

<div class="flex flex-col gap-6" data-course-risk-requirements>
    <x-admin.course.edit-badge-bar :data="get_defined_vars()" />

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Abilitazioni di rischio acquisite') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Seleziona i certificati ottenibili con questo corso e definisci una o più tipologie di validità per ciascuno.') }}
                    </p>
                </div>

                @if ($riskBasedRequirements->isNotEmpty())
                    <button type="button" class="btn btn-primary btn-sm" data-open-risk-requirement-selection-modal>
                        <span>{{ __('Aggiungi') }}</span>
                        <x-lucide-plus class="h-4 w-4" />
                    </button>
                @endif
            </div>

            <form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')

            <div class="flex flex-col gap-4">
                @if ($riskBasedRequirements->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
                        {{ __('Non ci sono ancora abilitazioni configurate.') }}
                    </div>
                @else
                    <div class="grid gap-3" data-course-risk-requirements-list></div>

                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70 hidden" data-course-risk-requirements-empty>
                        {{ __('Nessuna abilitazione associata al corso.') }}
                    </div>

                    <div data-course-risk-requirements-hidden-inputs></div>

                    <script type="application/json" data-course-risk-requirements-all>
                        {!! json_encode($allRiskBasedRequirementsPayload->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
                    </script>
                    <script type="application/json" data-course-risk-requirements-selected>
                        {!! json_encode($selectedRiskBasedRequirementsPayload->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
                    </script>

                    <dialog class="modal" data-course-risk-requirement-selection-modal>
                            <div class="modal-box max-w-4xl">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-semibold">{{ __('Seleziona requisito di rischio') }}</h3>
                                        <p class="text-sm text-base-content/70">
                                            {{ __('Scegli uno dei requisiti disponibili da associare al corso.') }}
                                        </p>
                                    </div>
                                    <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-risk-requirement-selection-modal>
                                        <x-lucide-x class="h-4 w-4" />
                                    </button>
                                </div>

                                <div class="mt-6 overflow-x-auto rounded-box border border-base-300">
                                    <table class="table w-full">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Nome') }}</th>
                                                <th>{{ __('Descrizione') }}</th>
                                                <th class="text-right">{{ __('Azioni') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody data-course-risk-requirement-selection-tbody></tbody>
                                    </table>
                                </div>

                                <div class="mt-4 rounded-box border border-dashed border-base-300 bg-base-100/70 p-4 text-sm text-base-content/70 hidden" data-course-risk-requirement-selection-empty>
                                    {{ __('Tutti i requisiti disponibili sono già associati al corso.') }}
                                </div>

                                <div class="modal-action">
                                    <button type="button" class="btn btn-ghost" data-close-risk-requirement-selection-modal>
                                        {{ __('Chiudi') }}
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="modal-backdrop" data-close-risk-requirement-selection-modal>
                                {{ __('Chiudi') }}
                            </button>
                    </dialog>

                    <dialog class="modal" data-course-risk-requirement-validity-modal>
                            <div class="modal-box max-w-lg">
                                <div class="space-y-2">
                                    <h3 class="text-lg font-semibold" data-course-risk-requirement-validity-modal-title>{{ __('Imposta validità del corso') }}</h3>
                                    <p class="text-sm text-base-content/70" data-course-risk-requirement-validity-modal-description></p>
                                </div>

                                <div class="mt-6 rounded-box border border-base-300 bg-base-200/40 p-4">
                                    <div class="space-y-2">
                                        <div class="font-medium text-base-content">{{ __('Validità del corso') }}</div>
                                        <p class="text-sm text-base-content/70">
                                            {{ __('Puoi selezionare una o più tipologie di validità per lo stesso requisito.') }}
                                        </p>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-3" data-course-risk-requirement-validity-options>
                                        @foreach ($courseRiskRequirementValidityTypeLabels as $value => $label)
                                            <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 bg-base-100 px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $value }}"
                                                    class="checkbox"
                                                    data-course-risk-requirement-validity-option
                                                >
                                                <span class="label-text font-medium">{{ $label }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="mt-4 hidden rounded-box border border-base-300 bg-base-200/40 p-4" data-course-risk-requirement-integrative-fields>
                                    <div class="space-y-2">
                                        <div class="font-medium text-base-content">{{ __('Livelli di partenza ammessi') }}</div>
                                        <p class="text-sm text-base-content/70">
                                            {{ __('Seleziona i livelli che l\'utente deve possedere per poter frequentare il corso integrativo.') }}
                                        </p>
                                    </div>

                                    <div class="mt-4 flex flex-col gap-2" data-course-risk-requirement-integrative-options>
                                        @foreach ($riskLevels as $riskLevel)
                                            <label class="label cursor-pointer justify-start gap-3">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $riskLevel->value }}"
                                                    class="checkbox"
                                                    data-integrative-start-level-option
                                                >
                                                <span class="badge badge-sm {{ $riskLevel->badgeColor() }} min-h-7 px-2 text-[11px]">{{ $riskLevel->label() }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="modal-action">
                                    <button type="button" class="btn btn-ghost" data-close-risk-requirement-validity-modal>
                                        {{ __('Annulla') }}
                                    </button>
                                    <button type="button" class="btn btn-primary" data-confirm-risk-requirement-validity>
                                        {{ __('Conferma') }}
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="modal-backdrop" data-close-risk-requirement-validity-modal>
                                {{ __('Chiudi') }}
                            </button>
                    </dialog>

                    <dialog class="modal" data-course-risk-requirement-delete-modal>
                            <div class="modal-box max-w-lg">
                                <div class="space-y-2">
                                    <h3 class="text-lg font-semibold">{{ __('Rimuovi associazione') }}</h3>
                                    <p class="text-sm text-base-content/70" data-course-risk-requirement-delete-modal-description></p>
                                </div>

                                <div class="modal-action">
                                    <button type="button" class="btn btn-ghost" data-close-risk-requirement-delete-modal>
                                        {{ __('Annulla') }}
                                    </button>
                                    <button type="button" class="btn btn-accent" data-confirm-risk-requirement-delete>
                                        {{ __('Conferma eliminazione') }}
                                    </button>
                                </div>
                            </div>
                            <button type="button" class="modal-backdrop" data-close-risk-requirement-delete-modal>
                                {{ __('Chiudi') }}
                            </button>
                    </dialog>
                @endif

                @error('risk_based_requirement_ids')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
                @error('risk_based_requirement_validity_types')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
                @error('risk_based_requirement_integrative_start_levels')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">
                    <span>{{ __('Salva dati') }}</span>
                    <x-lucide-save class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
            </form>
</div>
