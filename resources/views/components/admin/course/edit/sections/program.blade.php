@props([
    'course',
    'courseProgramSchedule',
    'courseProgramTeachingMethodLabels',
    'courseValidator',
    'updateUrl',
])

@php
    $rows = collect($courseProgramSchedule)->values();
@endphp

<div class="flex flex-col gap-6">
    <x-admin.course.edit-badge-bar :data="get_defined_vars()" />

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex-1">
                    <h2 class="card-title">{{ __('Programma corso') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Definisci orari, durata, metodologia e argomento delle attività.') }}
                    </p>
                </div>

                <button type="button" class="btn btn-primary sm:self-start" data-open-course-program-modal>
                    <x-lucide-plus class="h-4 w-4" />
                    <span>{{ __('Crea nuovo') }}</span>
                </button>
            </div>

            <form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6" data-course-program-form>
                @csrf
                @method('PUT')

                @if ($errors->has('program_schedule') || $errors->has('program_schedule.*'))
                    <div class="alert alert-error">
                        <x-lucide-circle-alert class="h-5 w-5" />
                        <span>{{ __('Controlla le righe del programma corso.') }}</span>
                    </div>
                @endif

                <div class="overflow-x-auto rounded-box border border-base-300">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Ora inizio') }}</th>
                                <th>{{ __('Ora fine') }}</th>
                                <th>{{ __('Durata modulo') }}</th>
                                <th>{{ __('Metodologie Didattiche') }}</th>
                                <th>{{ __('Argomento/sessione') }}</th>
                                <th class="w-16"></th>
                            </tr>
                        </thead>
                        <tbody data-course-program-rows>
                            @foreach ($rows as $index => $row)
                                @php
                                    $durationHours = $row['duration_hours'] ?? null;
                                    $durationMinutes = $row['duration_minutes'] ?? null;
                                @endphp
                                <tr data-course-program-row>
                                    <td data-program-display="starts_at">{{ $row['starts_at'] ?? __('n/d') }}</td>
                                    <td data-program-display="ends_at">{{ $row['ends_at'] ?? __('n/d') }}</td>
                                    <td data-program-display="duration">
                                        {{ filled($durationHours) || filled($durationMinutes) ? __(':hours h :minutes min', ['hours' => (int) $durationHours, 'minutes' => (int) $durationMinutes]) : __('n/d') }}
                                    </td>
                                    <td data-program-display="teaching_method">{{ $courseProgramTeachingMethodLabels[$row['teaching_method'] ?? ''] ?? __('n/d') }}</td>
                                    <td data-program-display="topic" class="max-w-md whitespace-pre-line">{{ $row['topic'] ?? __('n/d') }}</td>
                                    <td>
                                        @foreach (['starts_at', 'ends_at', 'duration_hours', 'duration_minutes', 'teaching_method', 'topic'] as $field)
                                            <input
                                                type="hidden"
                                                data-program-field="{{ $field }}"
                                                name="program_schedule[{{ $index }}][{{ $field }}]"
                                                value="{{ $row[$field] ?? '' }}"
                                            >
                                        @endforeach
                                        <button type="button" class="btn btn-ghost btn-sm btn-square" data-remove-course-program-row aria-label="{{ __('Rimuovi riga') }}">
                                            <x-lucide-trash-2 class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="{{ $rows->isEmpty() ? '' : 'hidden' }} border-t border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70" data-course-program-empty>
                        {{ __('Nessuna attività nel programma corso.') }}
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <span>{{ __('Salva dati') }}</span>
                        <x-lucide-save class="h-4 w-4" />
                    </button>
                </div>
            </form>
        </div>
    </div>

    <dialog class="modal" data-course-program-modal>
        <div class="modal-box max-w-3xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold">{{ __('Crea nuovo programma') }}</h3>
                    <p class="text-sm text-base-content/70">{{ __('Compila i dati della nuova attività.') }}</p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm btn-square" data-close-course-program-modal aria-label="{{ __('Chiudi') }}">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="form-control flex flex-col gap-2">
                    <label for="program_modal_starts_at" class="label p-0">
                        <span class="label-text font-medium">{{ __('Ora inizio') }}</span>
                    </label>
                    <input id="program_modal_starts_at" data-program-modal-field="starts_at" type="time" class="input input-bordered w-full">
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="program_modal_ends_at" class="label p-0">
                        <span class="label-text font-medium">{{ __('Ora fine') }}</span>
                    </label>
                    <input id="program_modal_ends_at" data-program-modal-field="ends_at" type="time" class="input input-bordered w-full">
                </div>

                <div class="form-control flex flex-col gap-2">
                    <span class="label p-0">
                        <span class="label-text font-medium">{{ __('Durata modulo') }}</span>
                    </span>
                    <div class="flex gap-2">
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <input data-program-modal-field="duration_hours" type="number" min="0" class="w-full" aria-label="{{ __('Ore') }}">
                            <span class="text-sm text-base-content/60">{{ __('h') }}</span>
                        </label>
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <input data-program-modal-field="duration_minutes" type="number" min="0" max="59" class="w-full" aria-label="{{ __('Minuti') }}">
                            <span class="text-sm text-base-content/60">{{ __('min') }}</span>
                        </label>
                    </div>
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="program_modal_teaching_method" class="label p-0">
                        <span class="label-text font-medium">{{ __('Metodologie Didattiche') }}</span>
                    </label>
                    <select id="program_modal_teaching_method" data-program-modal-field="teaching_method" class="select select-bordered w-full">
                        <option value="">{{ __('Seleziona') }}</option>
                        @foreach ($courseProgramTeachingMethodLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-control flex flex-col gap-2 md:col-span-2">
                    <label for="program_modal_topic" class="label p-0">
                        <span class="label-text font-medium">{{ __('Argomento/sessione') }}</span>
                    </label>
                    <textarea id="program_modal_topic" data-program-modal-field="topic" class="textarea textarea-bordered min-h-28 w-full"></textarea>
                </div>
            </div>

            <div class="modal-action">
                <button type="button" class="btn btn-ghost" data-close-course-program-modal>{{ __('Annulla') }}</button>
                <button type="button" class="btn btn-primary" data-confirm-course-program-modal>
                    <span>{{ __('Crea') }}</span>
                    <x-lucide-plus class="h-4 w-4" />
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Chiudi') }}</button>
        </form>
    </dialog>

    <script type="application/json" data-course-program-teaching-method-labels>@json($courseProgramTeachingMethodLabels)</script>

    <template data-course-program-row-template>
        <tr data-course-program-row>
            <td data-program-display="starts_at"></td>
            <td data-program-display="ends_at"></td>
            <td data-program-display="duration"></td>
            <td data-program-display="teaching_method"></td>
            <td data-program-display="topic" class="max-w-md whitespace-pre-line"></td>
            <td>
                @foreach (['starts_at', 'ends_at', 'duration_hours', 'duration_minutes', 'teaching_method', 'topic'] as $field)
                    <input type="hidden" data-program-field="{{ $field }}">
                @endforeach
                <button type="button" class="btn btn-ghost btn-sm btn-square" data-remove-course-program-row aria-label="{{ __('Rimuovi riga') }}">
                    <x-lucide-trash-2 class="h-4 w-4" />
                </button>
            </td>
        </tr>
    </template>
</div>
