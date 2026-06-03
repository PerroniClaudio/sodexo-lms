@php
    $availableTeacherAccountType = \Spatie\Permission\Models\Role::query()->where('name', 'teacher')->exists()
        ? 'teacher'
        : 'docente';
    $selectedAccountType = old('account_type', isset($user) ? ($user->getRoleNames()->first() ?? 'user') : 'user');
    $canManageAccountType = auth()->user()?->hasRole('superadmin') ?? false;
    $accountTypeLabels = collect([
        'user' => __('profile.options.account.user'),
        'admin' => __('profile.options.account.admin'),
        $availableTeacherAccountType => __('profile.options.account.teacher'),
        'tutor' => __('profile.options.account.tutor'),
    ]);

    if ($selectedAccountType === 'docente' && ! $accountTypeLabels->has('docente')) {
        $accountTypeLabels->put('docente', __('profile.options.account.teacher'));
    }

    if ($selectedAccountType === 'superadmin') {
        $accountTypeLabels->put('superadmin', __('Superadmin'));
    }

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

<div class="flex flex-col gap-4">
    <div>
        <div class="mb-2 mt-4">
            <span class="text-lg font-bold text-primary">{{ __('profile.sections.user') }}</span>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="form-control">
                <label for="account_type" class="label">
                    <span class="label-text font-semibold">{{ __('profile.fields.account_type') }} <span class="text-error">*</span></span>
                </label>
                @if (! isset($user) || $canManageAccountType)
                    <select name="account_type" id="account_type" class="select select-bordered w-full" required>
                        @foreach ($accountTypeLabels as $accountTypeValue => $accountTypeLabel)
                            <option value="{{ $accountTypeValue }}" @selected($selectedAccountType === $accountTypeValue)>{{ $accountTypeLabel }}</option>
                        @endforeach
                    </select>
                @else
                    <input type="hidden" name="account_type" value="{{ $selectedAccountType }}">
                    <input type="text" value="{{ $accountTypeLabels->get($selectedAccountType, ucfirst($selectedAccountType)) }}" class="input input-bordered w-full" readonly>
                @endif
                @error('account_type')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="email" class="label">
                    <span class="label-text font-semibold">Email <span class="text-error">*</span></span>
                </label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email ?? '') }}" class="input input-bordered w-full" required>
                @error('email')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="name" class="label">
                    <span class="label-text font-semibold">Nome <span class="text-error">*</span></span>
                </label>
                <input type="text" name="name" id="name" value="{{ old('name', $user->name ?? '') }}" class="input input-bordered w-full" required>
                @error('name')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="surname" class="label">
                    <span class="label-text font-semibold">Cognome <span class="text-error">*</span></span>
                </label>
                <input type="text" name="surname" id="surname" value="{{ old('surname', $user->surname ?? '') }}" class="input input-bordered w-full" required>
                @error('surname')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="fiscal_code" class="label">
                    <span class="label-text font-semibold">Codice Fiscale <span class="text-error">*</span></span>
                </label>
                <input type="text" name="fiscal_code" id="fiscal_code" value="{{ old('fiscal_code', $user->fiscal_code ?? '') }}" class="input input-bordered w-full" required>
                @error('fiscal_code')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="phone" class="label">
                    <span class="label-text font-semibold">Telefono</span>
                </label>
                <div class="flex gap-2">
                    <input type="text" name="phone_prefix" id="phone_prefix" class="input input-bordered w-fit flex-0" placeholder="+39" value="{{ old('phone_prefix', $user->phone_prefix ?? '+39') }}">
                    <input type="text" name="phone" id="phone" class="input input-bordered flex-1" placeholder="{{ __('forms.phone_number_placeholder') }}" value="{{ old('phone', $user->phone ?? '') }}">
                </div>
                @error('phone_prefix')<span class="text-error text-sm">{{ $message }}</span>@enderror
                @error('phone')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>

    <div class="user-extra-fields" data-user-only>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="form-control">
                <label for="birth_date" class="label">
                    <span class="label-text font-semibold">Data di nascita</span>
                </label>
                <input type="date" name="birth_date" id="birth_date" class="input input-bordered w-full" value="{{ old('birth_date', isset($user) && $user->birth_date ? $user->birth_date->format('Y-m-d') : '') }}">
                @error('birth_date')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="birth_place" class="label">
                    <span class="label-text font-semibold">Luogo di nascita</span>
                </label>
                <input type="text" name="birth_place" id="birth_place" class="input input-bordered w-full" value="{{ old('birth_place', $user->birth_place ?? '') }}">
                @error('birth_place')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control">
                <label for="gender" class="label">
                    <span class="label-text font-semibold">Genere</span>
                </label>
                <select name="gender" id="gender" class="select select-bordered w-full">
                    <option value="">{{ __('profile.options.unspecified') }}</option>
                    <option value="M" @selected(old('gender', $user->gender ?? '') == 'M')>{{ __('Maschio') }}</option>
                    <option value="F" @selected(old('gender', $user->gender ?? '') == 'F')>{{ __('Femmina') }}</option>
                </select>
                @error('gender')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control" data-user-only>
                <label for="is_foreigner_or_immigrant" class="label">
                    <span class="label-text font-semibold">Straniero/Immigrato <span class="text-error">*</span></span>
                </label>
                <select name="is_foreigner_or_immigrant" id="is_foreigner_or_immigrant" class="select select-bordered w-full" data-required="true" required>
                    <option value="" disabled {{ !isset($user) && old('is_foreigner_or_immigrant', null) === null ? 'selected' : '' }} hidden>{{ __('forms.select_placeholder') }}</option>
                    <option value="0" @selected(isset($user) ? (string) ($user->is_foreigner_or_immigrant) === '0' : old('is_foreigner_or_immigrant', null) === '0')>{{ __('profile.options.no') }}</option>
                    <option value="1" @selected(isset($user) ? (string) ($user->is_foreigner_or_immigrant) === '1' : old('is_foreigner_or_immigrant', null) === '1')>{{ __('profile.options.yes') }}</option>
                </select>
                @error('is_foreigner_or_immigrant')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="mb-2 mt-4">
            <span class="text-lg font-bold text-primary">{{ __('profile.sections.residence') }}</span>
        </div>

        <x-address-selector-simple
            :countryValue="old('country', $user->homeCountry?->code ?? 'it')"
            :regionValue="old('region', $user->homeRegion?->name ?? '')"
            :provinceValue="old('province', $user->homeProvince?->name ?? '')"
            :cityValue="old('city', $user->homeCity?->name ?? '')"
            :addressValue="old('address', $user->address ?? '')"
            :postalCodeValue="old('postal_code', $user->postal_code ?? '')"
            :required="false"
        />

        <div class="mb-2 mt-4">
            <span class="text-lg font-bold text-primary">{{ __('profile.sections.work') }}</span>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="form-control" data-user-only>
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

            <div class="form-control" data-user-only>
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

            <div class="form-control" data-user-only>
                <label for="job_sector_id" class="label">
                    <span class="label-text font-semibold">Settore <span class="text-error">*</span></span>
                </label>
                <select name="job_sector_id" id="job_sector_id" class="select select-bordered w-full" data-required="true" required>
                    <option value="">{{ __('Seleziona') }}</option>
                    @foreach($jobSectors as $sector)
                        <option value="{{ $sector->id }}" @selected(old('job_sector_id', $user->job_sector_id ?? '') == $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
                @error('job_sector_id')<span class="text-error text-sm">{{ $message }}</span>@enderror
            </div>

            <div class="form-control" data-user-only>
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

            <div class="form-control" data-user-only>
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

            <div class="form-control" data-user-only>
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

        <div class="rounded-box border border-base-300 bg-base-200/30 p-4 my-4" data-user-only data-job-task-assignments>
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
                        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
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

                            <button type="button" class="btn btn-error btn-outline btn-sm md:mb-1" data-remove-job-task-row>
                                <x-lucide-trash-2 class="h-4 w-4" />
                                <span>Rimuovi</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <x-job-unit-selector
            :units="$jobUnits"
            :selectedId="old('job_unit_id', $user->job_unit_id ?? null)"
            :label="__('Unita Produttiva')"
            :required="$selectedAccountType === 'user'"
            data-user-only
        />
    </div>
</div>

<template data-job-task-row-template>
    <div class="rounded-box border border-base-300 bg-base-100 p-4" data-job-task-row>
        <div class="grid gap-4 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
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

            <button type="button" class="btn btn-error btn-outline btn-sm md:mb-1" data-remove-job-task-row>
                <x-lucide-trash-2 class="h-4 w-4" />
                <span>Rimuovi</span>
            </button>
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
