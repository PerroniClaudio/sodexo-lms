<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8" data-admin-user-edit-page>
        <x-page-header :title="__('Modifica utente')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="flex flex-col gap-6" data-user-edit-form>
                    @csrf
                    @method('PUT')
                    @include('admin.users.partials.form')
                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">
                            {{ __('Annulla') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Aggiorna') }}</span>
                            <x-lucide-save class="h-4 w-4" />
                        </button>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const typeSelect = document.getElementById('account_type');
                            const userOnlyBlocks = document.querySelectorAll('[data-user-only]');

                            function toggleUserOnlyFields() {
                                if (!typeSelect) {
                                    return;
                                }

                                if (typeSelect.value === 'user') {
                                    userOnlyBlocks.forEach((block) => {
                                        block.style.display = '';
                                        block.querySelectorAll('input,select,textarea').forEach((element) => {
                                            if (element.dataset.originalName) {
                                                element.name = element.dataset.originalName;
                                            }

                                            element.disabled = false;
                                            if (element.dataset.required === 'true') {
                                                element.required = true;
                                            }
                                        });
                                    });
                                } else {
                                    userOnlyBlocks.forEach((block) => {
                                        block.style.display = 'none';
                                        block.querySelectorAll('input,select,textarea').forEach((element) => {
                                            if (!element.dataset.originalName) {
                                                element.dataset.originalName = element.name;
                                            }

                                            element.required = false;
                                            element.removeAttribute('name');
                                            element.disabled = true;
                                        });
                                    });
                                }
                            }

                            if (typeSelect) {
                                toggleUserOnlyFields();
                                typeSelect.addEventListener('change', toggleUserOnlyFields);
                            }
                        });
                    </script>
                </form>
            </div>
        </div>

        @php
            $riskSummaryCardClass = match ($riskSummary['risk_badge_class'] ?? 'badge-ghost') {
                'badge-success' => 'border-success/40 bg-success/5',
                'badge-warning' => 'border-warning/40 bg-warning/5',
                'badge-error' => 'border-error/40 bg-error/5',
                default => 'border-base-300 bg-base-100',
            };
        @endphp

        <div
            class="card border shadow-sm {{ $riskSummaryCardClass }}"
            data-risk-summary
            data-risk-summary-url="{{ route('admin.api.users.risk-summary', $user) }}"
        >
            <div class="card-body gap-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="card-title">{{ __('Rischio attuale') }}</h2>
                        <p class="text-sm text-base-content/70" data-risk-summary-message>
                            {{ $riskSummary['message'] }}
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="badge badge-lg {{ $riskSummary['risk_badge_class'] }}" data-risk-summary-badge>
                            {{ $riskSummary['risk_label'] ?? __('Non applicabile') }}
                        </span>
                        <a href="{{ route('admin.users.risk-course-selection', $user) }}" class="btn btn-primary btn-sm">
                            {{ __('Selezione manuale corso') }}
                        </a>
                    </div>
                </div>

                <div class="grid gap-3" data-risk-based-requirements-items>
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
                                    }}">
                                        {{ $riskBasedRequirement['status_label'] }}
                                    </span>
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
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-base-content/70">{{ __('Nessun requisito di rischio disponibile.') }}</p>
                    @endforelse
                </div>

                @if (! empty($riskSummary['future_risk_transitions']))
                    <div class="rounded-box border border-dashed border-base-300 bg-base-100/80 p-4">
                        <div class="space-y-1">
                            <h3 class="font-semibold text-base-content">{{ __('Variazioni di rischio future') }}</h3>
                            <p class="text-sm text-base-content/70">
                                {{ __('Le mansioni con decorrenza o termine futuro cambiano il rischio nelle date seguenti.') }}
                            </p>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3">
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
                @endif
            </div>
        </div>

        <div
            class="card border border-base-300 bg-base-100 shadow-sm"
            data-user-certificates
            data-index-url="{{ route('admin.api.users.certificates.index', $user) }}"
            data-store-url="{{ route('admin.api.users.certificates.store', $user) }}"
        >
            <div class="card-body gap-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="card-title">{{ __('Certificati utente') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Gestisci attestati interni ed esterni e i requisiti di rischio soddisfatti da ogni certificato.') }}
                        </p>
                    </div>

                    <button type="button" class="btn btn-primary" data-open-certificate-modal>
                        <x-lucide-plus class="h-4 w-4" />
                        <span>{{ __('Aggiungi certificato') }}</span>
                    </button>
                </div>

                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <label class="input input-bordered flex w-full max-w-md items-center gap-2">
                        <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                        <input type="search" class="grow" placeholder="{{ __('Cerca per nome o descrizione') }}" data-certificates-search-input>
                    </label>

                    <button type="button" class="btn btn-primary btn-outline" data-certificates-search-button>
                        {{ __('Cerca') }}
                    </button>
                </div>

                <div class="hidden rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/70" data-certificates-loading>
                    {{ __('Caricamento certificati in corso...') }}
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th><button type="button" class="inline-flex items-center gap-2" data-sort-key="name">{{ __('Nome') }}</button></th>
                                <th><button type="button" class="inline-flex items-center gap-2" data-sort-key="issued_at">{{ __('Data conseguimento') }}</button></th>
                                <th><button type="button" class="inline-flex items-center gap-2" data-sort-key="expires_at">{{ __('Data scadenza') }}</button></th>
                                <th><button type="button" class="inline-flex items-center gap-2" data-sort-key="is_internal">{{ __('Tipo') }}</button></th>
                                <th>{{ __('Tipologia documento') }}</th>
                                <th>{{ __('Requisiti di rischio') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody data-certificates-tbody></tbody>
                    </table>
                </div>

                <div class="rounded-box border border-dashed border-base-300 px-4 py-6 text-center text-sm text-base-content/70" data-certificates-empty>
                    {{ __('Nessun certificato disponibile per questo utente.') }}
                </div>

                <div class="flex flex-col gap-4 border-t border-base-300 pt-4 lg:flex-row lg:items-center lg:justify-between">
                    <p class="text-sm text-base-content/70" data-certificates-summary>0 certificati</p>
                    <div class="join" data-certificates-pagination></div>
                </div>
            </div>

            <dialog class="modal" data-certificate-modal>
                <div class="modal-box max-w-3xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold">{{ __('Aggiungi certificato') }}</h3>
                            <p class="text-sm text-base-content/70">
                                {{ __('Registra un attestato manualmente e collega i requisiti di rischio soddisfatti.') }}
                            </p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-certificate-modal>
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>

                    <form class="mt-6 flex flex-col gap-4" data-certificate-form>
                        <input type="hidden" name="certificate_id" value="">

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label class="label p-0" for="certificate_name">
                                    <span class="label-text font-medium">{{ __('Nome certificato') }}</span>
                                </label>
                                <input id="certificate_name" name="name" type="text" class="input input-bordered w-full" required>
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label class="label p-0" for="certificate_issued_at">
                                    <span class="label-text font-medium">{{ __('Data conseguimento') }}</span>
                                </label>
                                <input id="certificate_issued_at" name="issued_at" type="date" class="input input-bordered w-full" required>
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label class="label p-0" for="certificate_expires_at">
                                    <span class="label-text font-medium">{{ __('Data scadenza') }}</span>
                                </label>
                                <input id="certificate_expires_at" name="expires_at" type="date" class="input input-bordered w-full">
                            </div>

                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label class="label p-0" for="certificate_description">
                                    <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                                </label>
                                <textarea id="certificate_description" name="description" class="textarea textarea-bordered min-h-24 w-full"></textarea>
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label class="label p-0" for="certificate_file_path">
                                    <span class="label-text font-medium">{{ __('Percorso file') }}</span>
                                </label>
                                <input id="certificate_file_path" name="file_path" type="text" class="input input-bordered w-full" placeholder="certificates/user/example.pdf">
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label class="label p-0" for="certificate_document_type_id">
                                    <span class="label-text font-medium">{{ __('Tipologia documento') }}</span>
                                </label>
                                <select id="certificate_document_type_id" name="document_type_id" class="select select-bordered w-full">
                                    <option value="">{{ __('Nessuna') }}</option>
                                    @foreach ($documentTypes as $documentType)
                                        <option value="{{ $documentType->id }}">{{ $documentType->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label class="label p-0" for="certificate_internal_course_id">
                                    <span class="label-text font-medium">{{ __('Corso interno') }}</span>
                                </label>
                                <select id="certificate_internal_course_id" name="internal_course_id" class="select select-bordered w-full" @disabled($availableCourses->isEmpty())>
                                    @if ($availableCourses->isEmpty())
                                        <option value="">{{ __('Nessun corso completato') }}</option>
                                    @else
                                        <option value="">{{ __('Nessuno') }}</option>
                                        @foreach ($availableCourses as $course)
                                            <option value="{{ $course['id'] }}">
                                                {{ $course['title'] }}{{ $course['completed_at_label'] ? ' - completato il '.$course['completed_at_label'] : '' }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label class="label p-0" for="certificate_risk_based_requirement_ids">
                                    <span class="label-text font-medium">{{ __('Requisiti di rischio soddisfatti') }}</span>
                                </label>
                                <select id="certificate_risk_based_requirement_ids" name="risk_based_requirement_ids[]" class="select select-bordered min-h-48 w-full" multiple data-risk-based-requirements-select>
                                    @foreach ($allRiskBasedRequirements as $riskBasedRequirement)
                                        <option value="{{ $riskBasedRequirement->id }}">{{ $riskBasedRequirement->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" class="btn btn-ghost" data-close-certificate-modal>{{ __('Annulla') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Salva certificato') }}</button>
                        </div>
                    </form>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button>{{ __('Chiudi') }}</button>
                </form>
            </dialog>
        </div>
    </div>
</x-layouts.admin>
