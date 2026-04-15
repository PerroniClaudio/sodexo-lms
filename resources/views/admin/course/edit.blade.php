<x-layouts.admin>
    @php
        $moduleTypeIcons = [
            'video' => 'lucide-clapperboard',
            'res' => 'lucide-users',
            'live' => 'lucide-monitor-play',
            'learning_quiz' => 'lucide-badge-help',
            'satisfaction_quiz' => 'lucide-message-square-heart',
        ];
    @endphp

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-course-edit-page
        data-has-create-module-errors="{{ $errors->has('type') || $errors->has('title') ? 'true' : 'false' }}"
    >
        <x-page-header :title="__('Modifica corso')">
            <x-slot:actions>
                <button type="button" class="btn btn-accent btn-outline" data-open-delete-course-modal>
                    <x-lucide-trash-2 class="h-4 w-4" />
                    <span>{{ __('Delete course') }}</span>
                </button>
            </x-slot:actions>
        </x-page-header>

        <div class="flex flex-col gap-6">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="card-title">{{ __('Dati anagrafici') }}</h2>
                            <p class="text-sm text-base-content/70">
                                {{ __('Gestisci le informazioni principali del corso.') }}
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.courses.update', $course) }}" class="flex flex-col gap-6">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label for="title" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Titolo del corso') }}</span>
                                </label>
                                <input
                                    id="title"
                                    name="title"
                                    type="text"
                                    value="{{ old('title', $course->title) }}"
                                    class="input input-bordered w-full @error('title') input-error @enderror"
                                    required
                                >
                                @error('title')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label for="description" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                                </label>
                                <textarea
                                    id="description"
                                    name="description"
                                    class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
                                    required
                                >{{ old('description', $course->description) }}</textarea>
                                @error('description')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label for="year" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Anno del corso') }}</span>
                                </label>
                                <input
                                    id="year"
                                    name="year"
                                    type="number"
                                    value="{{ old('year', $course->year) }}"
                                    class="input input-bordered w-full @error('year') input-error @enderror"
                                    min="1900"
                                    max="2100"
                                    required
                                >
                                @error('year')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label for="expiry_date" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Data scadenza') }}</span>
                                </label>
                                <input
                                    id="expiry_date"
                                    name="expiry_date"
                                    type="date"
                                    value="{{ old('expiry_date', $course->expiry_date?->format('Y-m-d')) }}"
                                    class="input input-bordered w-full @error('expiry_date') input-error @enderror"
                                    required
                                >
                                @error('expiry_date')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label for="status" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Stato') }}</span>
                                </label>
                                <select
                                    id="status"
                                    name="status"
                                    class="select select-bordered w-full @error('status') select-error @enderror"
                                    required
                                >
                                    @foreach ($courseStatusLabels as $courseStatus => $courseStatusLabel)
                                        <option value="{{ $courseStatus }}" @selected(old('status', $course->status) === $courseStatus)>
                                            {{ $courseStatusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
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

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="card-title">{{ __('Moduli') }}</h2>
                            <p class="text-sm text-base-content/70">
                                {{ __('Aggiungi un nuovo modulo scegliendo la tipologia da creare.') }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="btn btn-primary"
                            data-open-module-modal
                        >
                            <span>{{ __('New module') }}</span>
                            <x-lucide-plus class="h-4 w-4" />
                        </button>
                    </div>

                    @if ($modules->isEmpty())
                        <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70">
                            {{ __('Nessun modulo presente per questo corso.') }}
                        </div>
                    @else
                        <div
                            class="grid gap-4"
                            data-modules-sortable-list
                            data-reorder-url="{{ route('admin.courses.modules.reorder', $course) }}"
                        >
                            @foreach ($modules as $module)
                                <div
                                    class="rounded-box border border-base-300 bg-base-100 p-4 transition-shadow"
                                    draggable="true"
                                    data-module-item
                                    data-module-id="{{ $module->id }}"
                                >
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-base-300 text-base-content/60 cursor-move">
                                                <x-lucide-move class="h-4 w-4" />
                                            </div>

                                            <div class="space-y-1">
                                                <p class="text-sm font-semibold text-base-content">
                                                    {{ $module->title }}
                                                </p>
                                                <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                                                    <span class="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-primary/8 px-3 py-1 text-xs font-medium tracking-wide text-primary">
                                                        <x-dynamic-component
                                                            :component="$moduleTypeIcons[$module->type] ?? 'lucide-shapes'"
                                                            class="h-3.5 w-3.5"
                                                        />
                                                        <span>{{ $moduleTypeLabels[$module->type] ?? $module->type }}</span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <span class="badge badge-ghost">
                                                {{ $moduleStatusLabels[$module->status] ?? $module->status }}
                                            </span>
                                            <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-secondary btn-sm">
                                                <x-lucide-pencil class="h-4 w-4" />
                                                {{ __('Edit') }}
                                            </a>
                                            <button
                                                type="button"
                                                class="btn btn-accent btn-sm"
                                                data-open-delete-module-modal
                                                data-modal-target="#delete-module-modal-{{ $module->id }}"
                                            >
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                                {{ __('Delete') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <dialog id="delete-module-modal-{{ $module->id }}" class="modal">
                                    <div class="modal-box max-w-lg">
                                        <div class="space-y-2">
                                            <h3 class="text-lg font-semibold">{{ __('Delete module') }}</h3>
                                            <p class="text-sm text-base-content/70">
                                                {{ __('This action will move the module to the trash. Do you want to continue?') }}
                                            </p>
                                        </div>

                                        <div class="modal-action mt-6">
                                            <form method="dialog">
                                                <button type="submit" class="btn btn-ghost">
                                                    {{ __('Cancel') }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.courses.modules.destroy', [$course, $module]) }}">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit" class="btn btn-accent">
                                                    <span>{{ __('Confirm deletion') }}</span>
                                                    <x-lucide-trash-2 class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <form method="dialog" class="modal-backdrop">
                                        <button type="submit">{{ __('Close') }}</button>
                                    </form>
                                </dialog>
                            @endforeach
                        </div>
                    @endif

                    <dialog id="create-module-modal" class="modal">
                        <div class="modal-box max-w-2xl">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold">{{ __('New module') }}</h3>
                                <p class="text-sm text-base-content/70">
                                    {{ __('Select a type, then confirm to create the module.') }}
                                </p>
                            </div>

                            <form method="POST" action="{{ route('admin.courses.modules.store', $course) }}" class="mt-6 space-y-6">
                                @csrf

                                <fieldset class="space-y-3">
                                    <legend class="text-sm font-medium text-base-content">
                                        {{ __('Module type') }}
                                    </legend>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @foreach ($moduleTypeLabels as $moduleType => $moduleTypeLabel)
                                            <label class="cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="type"
                                                    value="{{ $moduleType }}"
                                                    class="peer sr-only"
                                                    @checked(old('type') === $moduleType)
                                                >
                                                <span class="flex min-h-24 items-center rounded-box border border-base-300 bg-base-100 px-4 py-3 text-sm font-medium transition peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary hover:border-primary/40">
                                                    {{ $moduleTypeLabel }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    @error('type')
                                        <p class="text-sm text-error">{{ $message }}</p>
                                    @enderror
                                </fieldset>

                                <div id="module-title-field" class="form-control flex flex-col gap-2">
                                    <label for="module-title" class="label p-0">
                                        <span class="label-text font-medium">{{ __('Module title') }}</span>
                                    </label>
                                    <input
                                        id="module-title"
                                        name="title"
                                        type="text"
                                        value="{{ old('title') }}"
                                        class="input input-bordered w-full @error('title') input-error @enderror"
                                    >
                                    @error('title')
                                        <p class="text-sm text-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="modal-action mt-0">
                                    <button
                                        type="button"
                                        class="btn btn-ghost"
                                        data-close-module-modal
                                    >
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <span>{{ __('Confirm') }}</span>
                                        <x-lucide-check class="h-4 w-4" />
                                    </button>
                                </div>
                            </form>
                        </div>

                        <form method="dialog" class="modal-backdrop">
                            <button type="submit">{{ __('Close') }}</button>
                        </form>
                    </dialog>

                    <dialog id="delete-course-modal" class="modal">
                        <div class="modal-box max-w-lg">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold">{{ __('Delete course') }}</h3>
                                <p class="text-sm text-base-content/70">
                                    {{ __('This action will move the course to the trash. Do you want to continue?') }}
                                </p>
                            </div>

                            <div class="modal-action mt-6">
                                <form method="dialog">
                                    <button type="submit" class="btn btn-ghost" data-close-delete-course-modal>
                                        {{ __('Cancel') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.courses.destroy', $course) }}">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-accent">
                                        <span>{{ __('Confirm deletion') }}</span>
                                        <x-lucide-trash-2 class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </div>

                        <form method="dialog" class="modal-backdrop">
                            <button type="submit">{{ __('Close') }}</button>
                        </form>
                    </dialog>
                </div>
            </div>
        </div>
    </div>

    @vite('resources/js/pages/admin-course-edit.js')
</x-layouts.admin>
