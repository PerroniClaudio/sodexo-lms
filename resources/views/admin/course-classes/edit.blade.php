<x-layouts.admin>
    @php
        $courseClassPayload = $courseClassPayloads->first();
    @endphp

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-course-edit-page
    >
        <x-page-header :title="__('Modifica classe')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Indietro') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Classe: :class. Modulo: :module.', ['class' => $courseClass->name, 'module' => $module->title]) }}
        </x-page-header>

        <div
            class="space-y-6"
            data-course-classes
            data-standalone-class-page="true"
            data-initial-course-class-id="{{ $courseClassPayload['id'] }}"
            data-class-back-url="{{ route('admin.courses.modules.edit', [$course, $module]) }}"
            data-classes-index-url="{{ route('admin.courses.classes.index', $course) }}"
            data-classes-store-url="{{ route('admin.courses.classes.store', $course) }}"
            data-classes-search-users-url="{{ route('admin.courses.classes.search-users', $course) }}"
            data-classes-search-teachers-url="{{ route('admin.courses.classes.search-teachers', $course) }}"
            data-classes-search-tutors-url="{{ route('admin.courses.classes.search-tutors', $course) }}"
        >
            <script type="application/json" data-course-classes-initial>@json($courseClassPayloads)</script>

            <form class="space-y-6" data-course-class-form data-course-class-modal>
                <input type="hidden" name="module_id" value="{{ $module->getKey() }}">

                <section class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <h2 class="card-title text-base">{{ __('Dettagli classe') }}</h2>
                            <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Salvataggio...') }}">
                                <span>{{ __('Salva') }}</span>
                                <x-lucide-save class="h-4 w-4" />
                            </button>
                        </div>

                        <div class="form-control flex flex-col gap-2">
                            <label class="label p-0" for="course-class-name">
                                <span class="label-text font-medium">{{ __('Nome classe') }}</span>
                            </label>
                            <input id="course-class-name" type="text" class="input input-bordered w-full" name="name" required>
                        </div>
                    </div>
                </section>

                <section class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h2 class="card-title text-base">{{ __('Date e orari') }}</h2>
                                <p class="text-sm text-base-content/70">{{ __('Una classe può avere uno o più slot.') }}</p>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm" data-add-course-class-schedule>
                                <x-lucide-plus class="h-4 w-4" />
                                <span>{{ __('Aggiungi slot') }}</span>
                            </button>
                        </div>

                        <div class="space-y-4" data-course-class-schedules></div>
                    </div>
                </section>

                <p class="text-sm text-error hidden" data-course-class-form-error></p>
            </form>

            <div class="flex flex-col gap-6" data-course-class-edit-tools>
                @foreach ([
                    ['key' => 'users', 'title' => __('Utenti'), 'count' => 'data-class-detail-users', 'table' => 'data-class-assigned-users'],
                    ['key' => 'teachers', 'title' => __('Docenti'), 'count' => 'data-class-detail-teachers', 'table' => 'data-class-assigned-teachers'],
                    ['key' => 'tutors', 'title' => __('Tutor'), 'count' => 'data-class-detail-tutors', 'table' => 'data-class-assigned-tutors'],
                ] as $peopleCard)
                    <section class="card border border-base-300 bg-base-100 shadow-sm">
                        <div class="card-body gap-4">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <h3 class="card-title text-base">{{ $peopleCard['title'] }}</h3>
                                    <span class="badge badge-outline" {{ $peopleCard['count'] }}></span>
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" data-manage-class-{{ $peopleCard['key'] }}>
                                    <x-lucide-plus class="h-4 w-4" />
                                    <span>{{ __('Aggiungi') }}</span>
                                </button>
                            </div>

                            <div class="overflow-x-auto rounded-box border border-base-300">
                                <table class="table table-zebra w-full">
                                    <tbody {{ $peopleCard['table'] }}></tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>

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
    </div>

    @vite(['resources/js/pages/admin-course-edit.js'])
</x-layouts.admin>
