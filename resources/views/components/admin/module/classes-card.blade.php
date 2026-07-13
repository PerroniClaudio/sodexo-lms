@props(['data' => []])

@php
    extract($data);
@endphp

<div
    class="card border border-base-300 bg-base-100 shadow-sm"
    data-course-classes
    data-classes-index-url="{{ route('admin.courses.classes.index', $course) }}"
    data-classes-store-url="{{ route('admin.courses.classes.store', $course) }}"
    data-classes-search-users-url="{{ route('admin.courses.classes.search-users', $course) }}"
    data-classes-search-teachers-url="{{ route('admin.courses.classes.search-teachers', $course) }}"
    data-classes-search-tutors-url="{{ route('admin.courses.classes.search-tutors', $course) }}"
>
    <script type="application/json" data-course-classes-initial>@json($courseClassPayloads)</script>

    <div class="card-body gap-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="card-title">{{ __('Classi') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Gestisci gruppi di utenti, docenti e tutor per gli appuntamenti di questo modulo.') }}
                </p>
            </div>

            <button type="button" class="btn btn-primary" data-open-course-class-modal>
                <span>{{ __('Nuova classe') }}</span>
                <x-lucide-plus class="h-4 w-4" />
            </button>
        </div>

        <div class="relative" data-course-classes-table-container>
            <div class="pointer-events-none absolute inset-0 z-10 hidden items-center justify-center bg-base-100/70" data-course-classes-loader>
                <span class="loading loading-spinner loading-md"></span>
            </div>

            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th class="w-20">{{ __('ID') }}</th>
                            <th>{{ __('Nome classe') }}</th>
                            <th>{{ __('Numero utenti') }}</th>
                            <th>{{ __('Data') }}</th>
                            <th class="text-right">{{ __('Azioni') }}</th>
                        </tr>
                    </thead>
                    <tbody data-course-classes-tbody></tbody>
                </table>
            </div>
        </div>

        <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70 hidden" data-course-classes-empty>
            {{ __('Nessuna classe presente per questo modulo.') }}
        </div>
    </div>

    <dialog class="modal" data-course-class-modal>
        <div class="modal-box max-w-4xl">
            <x-admin.module.class-form :data="get_defined_vars()" />
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="submit">{{ __('Close') }}</button>
        </form>
    </dialog>

    <dialog class="modal" data-course-class-people-modal>
        <div class="modal-box max-w-4xl">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold" data-course-class-people-title></h3>
                    <p class="text-sm text-base-content/70" data-course-class-people-subtitle></p>
                </div>
                <span class="badge badge-outline h-fit" data-course-class-people-count></span>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <div class="flex gap-2">
                        <label class="input input-bordered flex flex-1 items-center gap-2">
                            <x-lucide-search class="h-4 w-4 text-base-content/60" />
                            <input type="search" class="grow" data-course-class-people-search placeholder="{{ __('Cerca nome, cognome, CF, email') }}">
                        </label>
                        <button type="button" class="btn btn-primary" data-course-class-people-search-button>{{ __('Cerca') }}</button>
                    </div>
                    <div class="rounded-box border border-base-300">
                        <table class="table table-zebra w-full">
                            <tbody data-course-class-people-results></tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-4">
                    <h4 class="font-semibold">{{ __('Assegnati') }}</h4>
                    <div class="rounded-box border border-base-300">
                        <table class="table table-zebra w-full">
                            <tbody data-course-class-people-assigned></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <p class="mt-4 text-sm text-error hidden" data-course-class-people-error></p>

            <div class="modal-action">
                <button type="button" class="btn btn-primary" data-course-class-people-confirm data-loading-text="{{ __('Salvataggio...') }}" disabled>
                    {{ __('Conferma selezione') }}
                </button>
                <button type="button" class="btn btn-accent" data-course-class-people-confirm-removal data-loading-text="{{ __('Salvataggio...') }}" disabled>
                    {{ __('Conferma rimozione') }}
                </button>
                <button type="button" class="btn btn-ghost" data-close-course-class-people-modal>{{ __('Chiudi') }}</button>
            </div>
        </div>
    </dialog>

    <template data-course-class-row-template>
        <tr>
            <td class="font-mono text-sm" data-class-id></td>
            <td class="font-medium" data-class-name></td>
            <td><span class="badge badge-primary badge-outline h-fit" data-class-users></span></td>
            <td data-class-starts></td>
            <td>
                <div class="flex justify-end gap-2">
                    <a class="btn btn-outline btn-sm" data-edit-class>
                        <x-lucide-pencil class="h-4 w-4" />
                        <span>{{ __('Modifica') }}</span>
                    </a>
                    <a class="btn btn-primary btn-sm hidden" data-attendance-class>
                        <x-lucide-clock class="h-4 w-4" />
                        <span>{{ __('Gestisci presenze') }}</span>
                    </a>
                    <button type="button" class="btn btn-accent btn-sm" data-delete-class>
                        <x-lucide-trash-2 class="h-4 w-4" />
                        <span>{{ __('Elimina') }}</span>
                    </button>
                </div>
            </td>
        </tr>
    </template>

    <template data-course-class-schedule-template>
        <div class="rounded-box border border-base-300 p-4" data-course-class-schedule-row>
            <div class="mb-4 flex items-center justify-between gap-3">
                <span class="text-sm font-medium">{{ __('Slot classe') }}</span>
                <button type="button" class="btn btn-ghost btn-sm" data-remove-course-class-schedule>
                    <x-lucide-trash-2 class="h-4 w-4" />
                </button>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="form-control flex flex-col gap-2">
                    <label class="label p-0">
                        <span class="label-text font-medium">{{ __('Data inizio') }}</span>
                    </label>
                    <input type="date" class="input input-bordered w-full" data-schedule-starts-date required>
                </div>
                <div class="form-control flex flex-col gap-2">
                    <label class="label p-0">
                        <span class="label-text font-medium">{{ __('Ora inizio') }}</span>
                    </label>
                    <input type="time" class="input input-bordered w-full" data-schedule-starts-time required>
                </div>
                <div class="form-control flex flex-col gap-2">
                    <label class="label p-0">
                        <span class="label-text font-medium">{{ __('Data fine') }}</span>
                    </label>
                    <input type="date" class="input input-bordered w-full" data-schedule-ends-date required>
                </div>
                <div class="form-control flex flex-col gap-2">
                    <label class="label p-0">
                        <span class="label-text font-medium">{{ __('Ora fine') }}</span>
                    </label>
                    <input type="time" class="input input-bordered w-full" data-schedule-ends-time required>
                </div>
            </div>
        </div>
    </template>
</div>
