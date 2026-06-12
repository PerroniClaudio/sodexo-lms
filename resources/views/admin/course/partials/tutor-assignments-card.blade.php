<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div
        class="card-body gap-6"
        data-tutor-assignments-table
        data-tutor-assignments-api-url="{{ route('admin.api.courses.tutor-enrollments.index', $course) }}"
        data-tutor-assignments-search-users-api-url="{{ route('admin.api.courses.tutor-enrollments.search-users', $course) }}"
        data-tutor-assignments-store-api-url="{{ route('admin.api.courses.tutor-enrollments.store', $course) }}"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="card-title">{{ __('Tutor del corso') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Assegna al corso utenti con ruolo tutor.') }}
                </p>
            </div>

            <button type="button" class="btn btn-primary" data-open-course-tutor-modal>
                <span>{{ __('Nuovo tutor') }}</span>
                <x-lucide-plus class="h-4 w-4" />
            </button>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <label class="label cursor-pointer justify-start gap-3 p-0">
                <input type="checkbox" class="checkbox" data-tutor-assignments-show-trashed>
                <span class="label-text">{{ __('Mostra eliminati') }}</span>
            </label>

            <div class="flex w-full max-w-xl items-center gap-2">
                <label class="input input-bordered flex w-full items-center gap-2">
                    <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                    <input
                        type="search"
                        class="grow"
                        data-tutor-assignments-search
                        placeholder="{{ __('Cerca nome, cognome, CF, email') }}"
                    >
                </label>
                <button type="button" class="btn btn-primary" data-tutor-assignments-search-button>
                    {{ __('Cerca') }}
                </button>
            </div>
        </div>

        <div class="relative" data-tutor-assignments-table-container>
            <div class="pointer-events-none absolute inset-0 z-10 hidden items-center justify-center bg-base-100/70" data-tutor-assignments-loader>
                <span class="loading loading-spinner loading-md"></span>
            </div>

            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-tutor-sort-key="surname">
                                    {{ __('Cognome') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-tutor-sort-icon="asc" data-tutor-sort-indicator="surname" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-tutor-sort-icon="desc" data-tutor-sort-indicator="surname" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-tutor-sort-icon="none" data-tutor-sort-indicator="surname" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-tutor-sort-key="name">
                                    {{ __('Nome') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-tutor-sort-icon="asc" data-tutor-sort-indicator="name" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-tutor-sort-icon="desc" data-tutor-sort-indicator="name" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-tutor-sort-icon="none" data-tutor-sort-indicator="name" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-tutor-sort-key="fiscal_code">
                                    {{ __('CF') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-tutor-sort-icon="asc" data-tutor-sort-indicator="fiscal_code" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-tutor-sort-icon="desc" data-tutor-sort-indicator="fiscal_code" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-tutor-sort-icon="none" data-tutor-sort-indicator="fiscal_code" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-tutor-sort-key="email">
                                    {{ __('Email') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-tutor-sort-icon="asc" data-tutor-sort-indicator="email" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-tutor-sort-icon="desc" data-tutor-sort-indicator="email" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-tutor-sort-icon="none" data-tutor-sort-indicator="email" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-tutor-sort-key="assigned_at">
                                    {{ __('Assegnato il') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-tutor-sort-icon="asc" data-tutor-sort-indicator="assigned_at" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-tutor-sort-icon="desc" data-tutor-sort-indicator="assigned_at" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-tutor-sort-icon="none" data-tutor-sort-indicator="assigned_at" />
                                </button>
                            </th>
                            <th class="sticky right-0 z-20 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">{{ __('Azioni') }}</th>
                        </tr>
                    </thead>
                    <tbody data-tutor-assignments-tbody></tbody>
                </table>
            </div>
        </div>

        <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70 hidden" data-tutor-assignments-empty>
            {{ __('Nessun tutor assegnato a questo corso.') }}
        </div>

        <div class="flex flex-col gap-3 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
            <p data-tutor-assignments-summary></p>
            <div class="join" data-tutor-assignments-pagination></div>
        </div>

        <template data-tutor-assignment-row-template>
            <tr class="hover:bg-base-200">
                <td data-cell="surname"></td>
                <td data-cell="name"></td>
                <td data-cell="fiscal_code"></td>
                <td data-cell="email"></td>
                <td data-cell="assigned_at"></td>
                <td class="sticky right-0 z-10 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">
                    <div class="flex flex-col gap-2 xl:flex-row">
                        <button type="button" class="btn btn-xs btn-error xl:btn-sm" data-action="delete">{{ __('Elimina') }}</button>
                        <button type="button" class="btn btn-xs btn-success xl:btn-sm" data-action="restore">{{ __('Ripristina') }}</button>
                    </div>
                </td>
            </tr>
        </template>

        <dialog class="modal" data-create-course-tutor-modal>
            <div class="modal-box max-w-3xl">
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold">{{ __('Nuovo tutor del corso') }}</h3>
                    <p class="text-sm text-base-content/70">
                        {{ __('Cerca utenti con ruolo tutor per nome, cognome, email, CF o ID e assegnali come tutor del corso.') }}
                    </p>
                </div>

                <div class="mt-6 flex items-center gap-2">
                    <label class="input input-bordered flex w-full items-center gap-2">
                        <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                        <input
                            type="search"
                            class="grow"
                            data-course-tutor-user-search
                            placeholder="{{ __('Cerca nome, cognome, CF, email o ID utente') }}"
                        >
                    </label>
                    <button type="button" class="btn btn-primary" data-course-tutor-user-search-button>
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
                        <tbody data-course-tutor-user-results></tbody>
                    </table>
                </div>

                <div class="mt-4 rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70 hidden" data-course-tutor-user-results-empty>
                    {{ __('Nessun tutor trovato.') }}
                </div>

                <template data-course-tutor-user-row-template>
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
                    <button type="button" class="btn btn-ghost" data-close-course-tutor-modal>
                        {{ __('Chiudi') }}
                    </button>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button type="submit">{{ __('Close') }}</button>
            </form>
        </dialog>

        <dialog class="modal" data-confirm-course-tutor-modal>
            <div class="modal-box max-w-lg">
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold">{{ __('Conferma assegnazione tutor') }}</h3>
                    <p class="text-sm text-base-content/70" data-confirm-course-tutor-message></p>
                </div>

                <div class="modal-action mt-6">
                    <form method="dialog">
                        <button type="submit" class="btn btn-ghost">{{ __('Annulla') }}</button>
                    </form>
                    <button type="button" class="btn btn-primary" data-confirm-course-tutor-submit data-loading-text="{{ __('Salvataggio...') }}">
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
