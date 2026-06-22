@props([
    'user' => null,
    'jobCategories' => [],
    'jobLevels' => [],
    'jobTasks' => [],
    'jobRoles' => [],
    'jobSectors' => [],
    'jobUnits' => [],
])

@php
    $isWorkerAccount = $user?->hasRole('user') ?? collect(old('roles', ['user']))->contains('user');
    $jobTaskAssignments = collect(old('job_tasks', isset($user)
        ? $user->jobTasks
            ->sortBy(fn ($task) => (string) ($task->pivot->starts_at ?? ''))
            ->map(fn ($task) => [
                'job_task_id' => (string) $task->id,
                'starts_at' => (string) ($task->pivot->starts_at ?? ''),
                'ends_at' => (string) ($task->pivot->ends_at ?? ''),
            ])
            ->values()
            ->all()
        : []));

    if ($jobTaskAssignments->isEmpty()) {
        $jobTaskAssignments = collect([[
            'job_task_id' => isset($user) ? (string) ($user->job_task_id ?? '') : '',
            'starts_at' => old('employment_start_date', isset($user) && $user->employment_start_date ? $user->employment_start_date->format('Y-m-d') : ''),
            'ends_at' => '',
        ]]);
    }

    $jobTaskOptions = collect($jobTasks)->map(fn ($task) => [
        'value' => (string) $task->id,
        'label' => $task->name,
        'search' => $task->name,
    ])->values()->all();
@endphp

<div class="flex flex-col gap-6" data-user-only-block>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="form-control">
            <label for="employment_start_date" class="label">
                <span class="label-text font-semibold">Data di assunzione <span class="text-error">*</span></span>
            </label>
            <input
                type="date"
                name="employment_start_date"
                id="employment_start_date"
                value="{{ old('employment_start_date', isset($user) && $user->employment_start_date ? $user->employment_start_date->format('Y-m-d') : '') }}"
                class="input input-bordered w-full"
                data-required="true"
                required
            >
            @error('employment_start_date')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="employment_end_date" class="label">
                <span class="label-text font-semibold">Data di cessazione</span>
            </label>
            <input
                type="date"
                name="employment_end_date"
                id="employment_end_date"
                value="{{ old('employment_end_date', isset($user) && $user->employment_end_date ? $user->employment_end_date->format('Y-m-d') : '') }}"
                class="input input-bordered w-full"
            >
            @error('employment_end_date')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control min-w-0">
            <label for="job_sector_id" class="label">
                <span class="label-text font-semibold">Settore <span class="text-error">*</span></span>
            </label>
            <label class="select select-bordered w-full min-w-0">
                <select name="job_sector_id" id="job_sector_id" class="min-w-0" data-required="true" required>
                    <option value="">{{ __('Seleziona') }}</option>
                    @foreach($jobSectors as $sector)
                        <option value="{{ $sector->id }}" @selected(old('job_sector_id', $user->job_sector_id ?? '') == $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
            </label>
            @error('job_sector_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="job_category_id" class="label">
                <span class="label-text font-semibold">Categoria</span>
            </label>
            <select name="job_category_id" id="job_category_id" class="select select-bordered w-full">
                <option value="">{{ __('Seleziona') }}</option>
                @foreach($jobCategories as $category)
                    <option value="{{ $category->id }}" @selected(old('job_category_id', $user->job_category_id ?? '') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('job_category_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="job_level_id" class="label">
                <span class="label-text font-semibold">Livello di inquadramento</span>
            </label>
            <select name="job_level_id" id="job_level_id" class="select select-bordered w-full">
                <option value="">{{ __('Seleziona') }}</option>
                @foreach($jobLevels as $level)
                    <option value="{{ $level->id }}" @selected(old('job_level_id', $user->job_level_id ?? '') == $level->id)>{{ $level->name }}</option>
                @endforeach
            </select>
            @error('job_level_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>

        <div class="form-control">
            <label for="job_role_id" class="label">
                <span class="label-text font-semibold">Ruolo <span class="text-error">*</span></span>
            </label>
            <select name="job_role_id" id="job_role_id" class="select select-bordered w-full" data-required="true" required>
                <option value="">{{ __('Seleziona') }}</option>
                @foreach($jobRoles as $role)
                    <option value="{{ $role->id }}" @selected(old('job_role_id', $user->job_role_id ?? '') == $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            @error('job_role_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="rounded-box border border-base-300 bg-base-200/30 p-4" data-job-task-assignments>
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="font-semibold">Mansioni assegnate <span class="text-error">*</span></div>
                <p class="text-sm text-base-content/70">
                    Inserisci una o piu mansioni con date di inizio e fine. Deve esserci sempre almeno una mansione attiva.
                </p>
            </div>
            <button type="button" class="btn btn-primary btn-outline btn-sm" data-add-job-task-row>
                <x-lucide-plus class="h-4 w-4" />
                <span>Aggiungi mansione</span>
            </button>
        </div>

        @error('job_tasks')
            <div class="mt-3 text-sm text-error">{{ $message }}</div>
        @enderror

        <div class="mt-4 flex flex-col gap-4" data-job-task-rows>
            @foreach($jobTaskAssignments as $index => $assignment)
                <div class="rounded-box border border-base-300 bg-base-100 p-4" data-job-task-row>
                    <div class="flex flex-col gap-4">
                        <div class="form-control">
                            <x-searchable-select
                                name="job_tasks[{{ $index }}][job_task_id]"
                                id="job_tasks_{{ $index }}_job_task_id"
                                error-key="job_tasks.{{ $index }}.job_task_id"
                                :selected-value="$assignment['job_task_id'] ?? ''"
                                :options="$jobTaskOptions"
                                :label="__('Mansione')"
                                :placeholder="__('Cerca o seleziona una mansione...')"
                                class="w-full"
                                data-required="true"
                                required
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label" for="job_tasks_{{ $index }}_starts_at">
                                    <span class="label-text font-semibold">Inizio</span>
                                </label>
                                <input
                                    type="date"
                                    name="job_tasks[{{ $index }}][starts_at]"
                                    id="job_tasks_{{ $index }}_starts_at"
                                    value="{{ $assignment['starts_at'] ?? '' }}"
                                    class="input input-bordered w-full"
                                    data-required="true"
                                    required
                                >
                                @error("job_tasks.$index.starts_at")<span class="text-error text-sm">{{ $message }}</span>@enderror
                            </div>

                            <div class="form-control">
                                <label class="label" for="job_tasks_{{ $index }}_ends_at">
                                    <span class="label-text font-semibold">Fine</span>
                                </label>
                                <input
                                    type="date"
                                    name="job_tasks[{{ $index }}][ends_at]"
                                    id="job_tasks_{{ $index }}_ends_at"
                                    value="{{ $assignment['ends_at'] ?? '' }}"
                                    class="input input-bordered w-full"
                                >
                                @error("job_tasks.$index.ends_at")<span class="text-error text-sm">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="button" class="btn btn-error btn-outline btn-sm" data-remove-job-task-row>
                                <x-lucide-trash-2 class="h-4 w-4" />
                                <span>Rimuovi</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <x-job-unit-selector
        :units="$jobUnits"
        :selectedId="old('job_unit_id', $user->job_unit_id ?? null)"
        :label="__('Unita Produttiva')"
        :required="$isWorkerAccount"
    />
</div>

<template data-job-task-row-template>
    <div class="rounded-box border border-base-300 bg-base-100 p-4" data-job-task-row>
        <div class="flex flex-col gap-4">
            <div class="form-control">
                <x-searchable-select
                    name="job_tasks[__INDEX__][job_task_id]"
                    id="job_tasks___INDEX___job_task_id"
                    :options="$jobTaskOptions"
                    :label="__('Mansione')"
                    :placeholder="__('Cerca o seleziona una mansione...')"
                    class="w-full"
                    data-required="true"
                    required
                />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Inizio</span>
                    </label>
                    <input type="date" name="job_tasks[__INDEX__][starts_at]" class="input input-bordered w-full" data-required="true" required>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Fine</span>
                    </label>
                    <input type="date" name="job_tasks[__INDEX__][ends_at]" class="input input-bordered w-full">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" class="btn btn-error btn-outline btn-sm" data-remove-job-task-row>
                    <x-lucide-trash-2 class="h-4 w-4" />
                    <span>Rimuovi</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const assignmentsContainer = document.querySelector('[data-job-task-assignments]');
        const rowsContainer = assignmentsContainer?.querySelector('[data-job-task-rows]');
        const addButton = assignmentsContainer?.querySelector('[data-add-job-task-row]');
        const template = document.querySelector('[data-job-task-row-template]');

        if (!assignmentsContainer || !rowsContainer || !addButton || !template) {
            return;
        }

        const bindRemoveButtons = () => {
            rowsContainer.querySelectorAll('[data-remove-job-task-row]').forEach((button) => {
                button.onclick = () => {
                    const rows = rowsContainer.querySelectorAll('[data-job-task-row]');

                    if (rows.length === 1) {
                        rows[0].querySelectorAll('select, input').forEach((element) => {
                            element.value = '';
                        });

                        return;
                    }

                    button.closest('[data-job-task-row]')?.remove();
                };
            });
        };

        addButton.addEventListener('click', () => {
            const nextIndex = rowsContainer.querySelectorAll('[data-job-task-row]').length;
            rowsContainer.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex)));
            window.initSearchableSelects?.(rowsContainer);
            bindRemoveButtons();
        });

        bindRemoveButtons();
    });
</script>
