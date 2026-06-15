@props([
    'course',
    'courseValidator',
    'jobRoles',
    'jobTasks',
    'jobUnits',
    'updateUrl',
])

@php
    $selectedJobRoleIds = collect(old(
        'job_role_ids',
        $course->jobRoles->pluck('id')->map(fn ($id) => (string) $id)->all(),
    ))->map(fn ($id) => (string) $id);
    $selectedJobTaskIds = collect(old(
        'job_task_ids',
        $course->jobTasks->pluck('id')->map(fn ($id) => (string) $id)->all(),
    ))->map(fn ($id) => (string) $id);
    $selectedJobUnitIds = collect(old(
        'job_unit_ids',
        $course->jobUnits->pluck('id')->map(fn ($id) => (string) $id)->all(),
    ))->map(fn ($id) => (string) $id);
@endphp

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div>
                <h2 class="card-title">{{ __('Destinatari') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Limita la visibilità del corso in base ai dati lavorativi degli utenti iscritti.') }}
                </p>
            </div>

            <form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6" data-course-recipients-form>
                @csrf
                @method('PUT')

                <label class="flex items-start gap-3 rounded-box border border-base-300 bg-base-200/40 p-4">
                    <input
                        type="checkbox"
                        name="visible_to_all"
                        value="1"
                        class="checkbox checkbox-primary mt-1"
                        data-auto-submit
                        @checked(old('visible_to_all', $course->visible_to_all))
                    >
                    <span>
                        <span class="block font-medium">{{ __('Visibile a tutti') }}</span>
                        <span class="block text-sm text-base-content/70">{{ __('Se attivo, le selezioni sotto non limitano il corso.') }}</span>
                    </span>
                </label>

                <x-admin.course.edit.sections.recipient-table
                    :items="$jobRoles"
                    :selected-ids="$selectedJobRoleIds"
                    input-name="job_role_ids"
                    :title="__('Ruoli')"
                    :empty-message="__('Nessun ruolo disponibile.')"
                />

                <x-admin.course.edit.sections.recipient-table
                    :items="$jobTasks"
                    :selected-ids="$selectedJobTaskIds"
                    input-name="job_task_ids"
                    :title="__('Mansioni')"
                    :empty-message="__('Nessuna mansione disponibile.')"
                />

                <x-admin.course.edit.sections.recipient-table
                    :items="$jobUnits"
                    :selected-ids="$selectedJobUnitIds"
                    input-name="job_unit_ids"
                    :title="__('Unità produttive')"
                    :empty-message="__('Nessuna unità produttiva disponibile.')"
                />

                @foreach (['visible_to_all', 'job_role_ids', 'job_role_ids.*', 'job_task_ids', 'job_task_ids.*', 'job_unit_ids', 'job_unit_ids.*'] as $errorKey)
                    @error($errorKey)
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                @endforeach

                <p class="hidden text-sm" data-course-recipients-status></p>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <span>{{ __('Salva') }}</span>
                        <x-lucide-save class="h-4 w-4" />
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
