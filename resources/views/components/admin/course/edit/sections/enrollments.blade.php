@props([
    'course',
    'courseValidator',
])

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div
            class="card-body gap-6"
            data-enrollments-table
            data-enrollments-api-url="{{ route('admin.api.courses.enrollments.index', $course) }}"
            data-enrollments-search-users-api-url="{{ route('admin.api.courses.enrollments.search-users', $course) }}"
            data-enrollments-store-api-url="{{ route('admin.api.courses.enrollments.store', $course) }}"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Iscritti') }}</h2>
                    @if ($course->status === 'draft')
                        <p class="text-sm text-base-content/70">
                            {{ __('Il corso è in stato bozza, non è possibile aggiungere iscritti finché non viene pubblicato.') }}
                        </p>
                    @else
                        <p class="text-sm text-base-content/70">
                            {{ __('Gestisci gli iscritti al corso. Puoi aggiungere nuovi utenti o rimuovere quelli esistenti.') }}
                        </p>
                    @endif
                </div>

                <button
                    type="button"
                    class="btn btn-primary"
                    data-open-iscritto-modal
                >
                    <span>{{ __('Nuovo iscritto') }}</span>
                    <x-lucide-plus class="h-4 w-4" />
                </button>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <label class="label cursor-pointer justify-start gap-3 p-0">
                    <input type="checkbox" class="checkbox" data-enrollments-show-trashed>
                    <span class="label-text">{{ __('Mostra eliminati') }}</span>
                </label>

                <div class="flex w-full max-w-xl items-center gap-2">
                    <label class="input input-bordered flex w-full items-center gap-2">
                        <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                        <input
                            type="search"
                            class="grow"
                            data-enrollments-search
                            placeholder="{{ __('Cerca nome, cognome, CF, email') }}"
                        >
                    </label>
                    <button type="button" class="btn btn-primary" data-enrollments-search-button>
                        {{ __('Cerca') }}
                    </button>
                </div>
            </div>

            <div class="relative" data-enrollments-table-container>
                <div class="pointer-events-none absolute inset-0 z-10 hidden items-center justify-center bg-base-100/70" data-enrollments-loader>
                    <span class="loading loading-spinner loading-md"></span>
                </div>

                <div class="overflow-x-auto rounded-box border border-base-300">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="surname">
                                        {{ __('Cognome') }}
                                        <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="surname" />
                                        <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="surname" />
                                        <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="surname" />
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="name">
                                        {{ __('Nome') }}
                                        <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="name" />
                                        <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="name" />
                                        <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="name" />
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="fiscal_code">
                                        {{ __('CF') }}
                                        <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="fiscal_code" />
                                        <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="fiscal_code" />
                                        <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="fiscal_code" />
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="email">
                                        {{ __('Email') }}
                                        <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="email" />
                                        <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="email" />
                                        <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="email" />
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="status">
                                        {{ __('Stato iscrizione') }}
                                        <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="status" />
                                        <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="status" />
                                        <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="status" />
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="completion_percentage">
                                        {{ __('Completamento') }}
                                        <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="completion_percentage" />
                                        <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="completion_percentage" />
                                        <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="completion_percentage" />
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="assigned_at">
                                        {{ __('Assegnato il') }}
                                        <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="assigned_at" />
                                        <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="assigned_at" />
                                        <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="assigned_at" />
                                    </button>
                                </th>
                                <th class="sticky right-0 z-20 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody data-enrollments-tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70 hidden" data-enrollments-empty>
                {{ __('Nessun iscritto presente per questo corso.') }}
            </div>

            <div class="flex flex-col gap-3 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
                <p data-enrollments-summary></p>
                <div class="join" data-enrollments-pagination></div>
            </div>

            <template data-enrollment-row-template>
                <tr class="hover:bg-base-200">
                    <td data-cell="surname"></td>
                    <td data-cell="name"></td>
                    <td data-cell="fiscal_code"></td>
                    <td data-cell="email"></td>
                    <td>
                        <span class="badge badge-outline h-fit" data-cell="status"></span>
                    </td>
                    <td data-cell="completion_percentage"></td>
                    <td data-cell="assigned_at"></td>
                    <td class="sticky right-0 z-10 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">
                        <div class="flex flex-col gap-2 xl:flex-row">
                            <button type="button" class="btn btn-xs btn-error xl:btn-sm" data-action="delete">{{ __('Elimina') }}</button>
                            <button type="button" class="btn btn-xs btn-success xl:btn-sm" data-action="restore">{{ __('Ripristina') }}</button>
                        </div>
                    </td>
                </tr>
            </template>

            <dialog id="create-enrollment-modal" class="modal" data-create-enrollment-modal>
                <div class="modal-box max-w-3xl">
                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold">{{ __('Nuovo iscritto') }}</h3>
                        <p class="text-sm text-base-content/70">
                            {{ __('Cerca un utente per nome, cognome, codice fiscale, email o ID e selezionalo per iscriverlo al corso.') }}
                        </p>
                    </div>

                    <div class="mt-6 flex items-center gap-2">
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                class="grow"
                                data-enrollment-user-search
                                placeholder="{{ __('Cerca nome, cognome, CF, email o ID utente') }}"
                            >
                        </label>
                        <button type="button" class="btn btn-primary" data-enrollment-user-search-button>
                            {{ __('Cerca') }}
                        </button>
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-box border border-base-300">
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
                            <tbody data-enrollment-user-results></tbody>
                        </table>
                    </div>

                    <div class="mt-4 hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70" data-enrollment-user-results-empty>
                        {{ __('Nessun utente trovato.') }}
                    </div>

                    <template data-enrollment-user-row-template>
                        <tr class="hover:bg-base-200">
                            <td data-cell="id"></td>
                            <td data-cell="surname"></td>
                            <td data-cell="name"></td>
                            <td data-cell="fiscal_code"></td>
                            <td data-cell="email"></td>
                            <td>
                                <button type="button" class="btn btn-primary btn-sm" data-action="select-user">
                                    {{ __('Seleziona') }}
                                </button>
                            </td>
                        </tr>
                    </template>

                    <div class="modal-action mt-6">
                        <button type="button" class="btn btn-ghost" data-close-create-enrollment-modal>
                            {{ __('Chiudi') }}
                        </button>
                    </div>
                </div>

                <form method="dialog" class="modal-backdrop">
                    <button type="submit">{{ __('Close') }}</button>
                </form>
            </dialog>

            <dialog id="confirm-enrollment-modal" class="modal" data-confirm-enrollment-modal>
                <div class="modal-box max-w-lg">
                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold">{{ __('Conferma iscrizione') }}</h3>
                        <p class="text-sm text-base-content/70" data-confirm-enrollment-message></p>
                    </div>

                    <div class="modal-action mt-6">
                        <form method="dialog">
                            <button type="submit" class="btn btn-ghost">{{ __('Annulla') }}</button>
                        </form>
                        <button type="button" class="btn btn-primary" data-confirm-enrollment-submit data-loading-text="{{ __('Salvataggio...') }}">
                            {{ __('Conferma') }}
                        </button>
                    </div>
                </div>

                <form method="dialog" class="modal-backdrop">
                    <button type="submit">{{ __('Close') }}</button>
                </form>
            </dialog>
        </div>
    </div>
</div>
