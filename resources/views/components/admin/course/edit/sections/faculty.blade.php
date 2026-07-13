@props([
    'course',
    'courseValidator',
    'roleLabels',
])

<div class="flex flex-col gap-6">
    <x-admin.course.edit-badge-bar :data="get_defined_vars()" />

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div
            class="card-body gap-6"
            data-faculty-table
            data-faculty-api-url="{{ route('admin.api.courses.faculty-members.index', $course) }}"
            data-faculty-search-users-api-url="{{ route('admin.api.courses.faculty-members.search-users', $course) }}"
            data-faculty-store-api-url="{{ route('admin.api.courses.faculty-members.store', $course) }}"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Faculty') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Gestisci il personale coinvolto nello svolgimento del corso.') }}
                    </p>
                </div>

                <button type="button" class="btn btn-primary" data-open-faculty-modal>
                    <span>{{ __('Nuovo membro Faculty') }}</span>
                    <x-lucide-plus class="h-4 w-4" />
                </button>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <label class="label cursor-pointer justify-start gap-3 p-0">
                    <input type="checkbox" class="checkbox" data-faculty-show-trashed>
                    <span class="label-text">{{ __('Mostra eliminati') }}</span>
                </label>

                <div class="flex w-full max-w-xl items-center gap-2">
                    <label class="input input-bordered flex w-full items-center gap-2">
                        <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                        <input type="search" class="grow" data-faculty-search placeholder="{{ __('Cerca nome, cognome, CF, ruolo, affiliazione') }}">
                    </label>
                    <button type="button" class="btn btn-primary" data-faculty-search-button>{{ __('Cerca') }}</button>
                </div>
            </div>

            <div class="relative" data-faculty-table-container>
                <div class="pointer-events-none absolute inset-0 z-10 hidden items-center justify-center bg-base-100/70" data-faculty-loader>
                    <span class="loading loading-spinner loading-md"></span>
                </div>

                <div class="overflow-x-auto rounded-box border border-base-300">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>{{ __('Cognome') }}</th>
                                <th>{{ __('Nome') }}</th>
                                <th>{{ __('CF') }}</th>
                                <th>{{ __('Ruolo') }}</th>
                                <th>{{ __('Affiliazione') }}</th>
                                <th>{{ __('Compenso') }}</th>
                                <th class="sticky right-0 z-20 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody data-faculty-tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70" data-faculty-empty>
                {{ __('Nessun membro Faculty presente per questo corso.') }}
            </div>

            <div class="flex flex-col gap-3 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
                <p data-faculty-summary></p>
                <div class="join" data-faculty-pagination></div>
            </div>

            <template data-faculty-row-template>
                <tr class="hover:bg-base-200">
                    <td data-cell="surname"></td>
                    <td data-cell="name"></td>
                    <td data-cell="fiscal_code"></td>
                    <td><span class="badge badge-outline h-fit" data-cell="role"></span></td>
                    <td>
                        <span class="block max-w-48 truncate xl:max-w-64" data-cell="affiliation"></span>
                    </td>
                    <td data-cell="compensation_amount"></td>
                    <td class="sticky right-0 z-10 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">
                        <div class="flex flex-col gap-2 xl:flex-row xl:flex-wrap">
                            <button type="button" class="btn btn-xs btn-info xl:btn-sm" data-action="edit">{{ __('Modifica') }}</button>
                            <button type="button" class="btn btn-xs btn-error xl:btn-sm" data-action="delete">{{ __('Elimina') }}</button>
                            <button type="button" class="btn btn-xs btn-success xl:btn-sm" data-action="restore">{{ __('Ripristina') }}</button>
                        </div>
                    </td>
                </tr>
            </template>

            <div class="card border border-base-300 bg-base-200/30 shadow-sm">
                <div class="card-body gap-4">
                    <div>
                        <h3 class="card-title text-base">{{ __('Documenti Faculty') }}</h3>
                        <p class="text-sm text-base-content/70">{{ __('Seleziona il tipo di documento e poi il membro Faculty per cui generarlo.') }}</p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button type="button" class="btn btn-outline" data-open-faculty-document-modal data-document-type="letter">
                            {{ __('Genera lettera di incarico') }}
                        </button>
                        <button type="button" class="btn btn-outline" data-open-faculty-document-modal data-document-type="certificate">
                            {{ __('Genera attestato') }}
                        </button>
                    </div>
                </div>
            </div>

            <dialog class="modal" data-create-faculty-modal>
                <div class="modal-box max-w-4xl">
                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold" data-faculty-modal-title>{{ __('Nuovo membro Faculty') }}</h3>
                        <p class="text-sm text-base-content/70" data-faculty-modal-description>{{ __('Seleziona un utente esistente o inserisci una nuova anagrafica Faculty.') }}</p>
                    </div>

                    <form class="mt-6 flex flex-col gap-4" data-faculty-form>
                        <div class="join">
                            <input class="btn join-item" type="radio" name="mode" value="existing" aria-label="{{ __('Utente esistente') }}" checked data-faculty-mode>
                            <input class="btn join-item" type="radio" name="mode" value="manual" aria-label="{{ __('Nuovo utente') }}" data-faculty-mode>
                        </div>

                        <div class="flex flex-col gap-3" data-faculty-panel="existing">
                            <div class="form-control flex flex-col gap-2">
                                <span class="label-text">{{ __('Cerca utente esistente') }}</span>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <label class="input input-bordered flex w-full items-center gap-2">
                                        <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                        <input type="search" class="grow" data-faculty-user-search placeholder="{{ __('Cerca nome, cognome, CF, email o ID utente') }}">
                                    </label>
                                    <button type="button" class="btn btn-primary sm:self-end" data-faculty-user-search-button>{{ __('Cerca') }}</button>
                                </div>
                            </div>

                            <div class="overflow-x-auto rounded-box border border-base-300">
                                <table class="table table-zebra w-full">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>{{ __('Cognome') }}</th>
                                            <th>{{ __('Nome') }}</th>
                                            <th>{{ __('CF') }}</th>
                                            <th>{{ __('Email') }}</th>
                                            <th>{{ __('Azioni') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody data-faculty-user-results></tbody>
                                </table>
                            </div>

                            <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-center text-sm text-base-content/70" data-faculty-user-results-empty>
                                {{ __('Nessun utente trovato.') }}
                            </div>
                        </div>

                        <div class="hidden grid-cols-1 gap-3 md:grid-cols-2" data-faculty-panel="manual">
                            <div class="form-control flex flex-col gap-2">
                                <span class="label-text">{{ __('Cognome') }}</span>
                                <input type="text" class="input input-bordered w-full" name="surname" data-faculty-manual-field>
                            </div>
                            <div class="form-control flex flex-col gap-2">
                                <span class="label-text">{{ __('Nome') }}</span>
                                <input type="text" class="input input-bordered w-full" name="name" data-faculty-manual-field>
                            </div>
                            <div class="form-control flex flex-col gap-2">
                                <span class="label-text">{{ __('Codice fiscale') }}</span>
                                <input type="text" class="input input-bordered w-full" name="fiscal_code" data-faculty-manual-field>
                            </div>
                        </div>

                        <template data-faculty-user-row-template>
                            <tr class="hover:bg-base-200">
                                <td data-cell="id"></td>
                                <td data-cell="surname"></td>
                                <td data-cell="name"></td>
                                <td data-cell="fiscal_code"></td>
                                <td data-cell="email"></td>
                                <td><button type="button" class="btn btn-primary btn-sm" data-action="select-user">{{ __('Seleziona') }}</button></td>
                            </tr>
                        </template>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="form-control flex flex-col gap-2">
                                <span class="label-text">{{ __('Ruolo') }}</span>
                                <select class="select select-bordered" name="role" required>
                                    @foreach ($roleLabels as $role => $label)
                                        <option value="{{ $role }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <span class="label-text">{{ __('Affiliazione') }}</span>
                                <textarea
                                    class="textarea textarea-bordered min-h-24 w-full"
                                    name="affiliation"
                                ></textarea>
                            </div>
                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label class="label cursor-pointer justify-start gap-3 p-0">
                                    <input type="checkbox" class="checkbox" name="has_compensation" value="1" data-faculty-has-compensation>
                                    <span class="label-text">{{ __('Previsto compenso') }}</span>
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="input input-bordered w-full md:max-w-sm"
                                    name="compensation_amount"
                                    data-faculty-compensation-amount
                                    placeholder="{{ __('Compenso in euro') }}"
                                    disabled
                                >
                            </div>
                        </div>
                    </form>

                    <div class="modal-action mt-6">
                        <button type="button" class="btn btn-ghost" data-close-faculty-modal>{{ __('Chiudi') }}</button>
                        <button type="button" class="btn btn-primary" data-save-faculty data-loading-text="{{ __('Salvataggio...') }}">{{ __('Salva') }}</button>
                    </div>
                </div>

                <form method="dialog" class="modal-backdrop">
                    <button type="submit">{{ __('Close') }}</button>
                </form>
            </dialog>

            <dialog class="modal" data-faculty-document-modal>
                <div class="modal-box max-w-2xl">
                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold" data-faculty-document-modal-title>{{ __('Seleziona membro Faculty') }}</h3>
                        <p class="text-sm text-base-content/70" data-faculty-document-modal-description>{{ __('Scegli il membro Faculty per continuare.') }}</p>
                    </div>

                    <div class="mt-6 overflow-x-auto rounded-box border border-base-300">
                        <table class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('Cognome') }}</th>
                                    <th>{{ __('Nome') }}</th>
                                    <th>{{ __('Ruolo') }}</th>
                                    <th>{{ __('Azione') }}</th>
                                </tr>
                            </thead>
                            <tbody data-faculty-document-results></tbody>
                        </table>
                    </div>

                    <div class="mt-4 hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-center text-sm text-base-content/70" data-faculty-document-results-empty>
                        {{ __('Nessun membro Faculty disponibile.') }}
                    </div>

                    <template data-faculty-document-row-template>
                        <tr class="hover:bg-base-200">
                            <td data-cell="surname"></td>
                            <td data-cell="name"></td>
                            <td data-cell="role"></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" data-action="generate-document">
                                    {{ __('Seleziona') }}
                                </button>
                            </td>
                        </tr>
                    </template>

                    <div class="modal-action mt-6">
                        <button type="button" class="btn btn-ghost" data-close-faculty-document-modal>{{ __('Chiudi') }}</button>
                    </div>
                </div>

                <form method="dialog" class="modal-backdrop">
                    <button type="submit">{{ __('Close') }}</button>
                </form>
            </dialog>
        </div>
    </div>
</div>
