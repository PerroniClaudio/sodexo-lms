@props([
    'course',
    'courseValidator',
    'creatableModuleTypeLabels',
    'moduleStatusLabels',
    'moduleTypeIcons',
    'moduleTypeLabels',
    'modules',
])

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Moduli') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Aggiungi un nuovo modulo scegliendo la tipologia da creare.') }}
                    </p>
                </div>

                <span
                    @class([
                        'tooltip tooltip-left' => $course->status === 'published',
                    ])
                    @if ($course->status === 'published')
                        data-tip="{{ __('Non puoi aggiungere nuovi moduli mentre il corso è pubblicato.') }}"
                    @endif
                >
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-open-module-modal
                        @disabled($course->status === 'published')
                    >
                        <span>{{ __('New module') }}</span>
                        <x-lucide-plus class="h-4 w-4" />
                    </button>
                </span>
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
                            draggable="{{ $module->isSatisfactionQuiz() ? 'false' : 'true' }}"
                            data-module-item
                            data-module-id="{{ $module->id }}"
                            data-module-type="{{ $module->type }}"
                        >
                            <div class="flex gap-3">
                                <div class="mt-0.5 flex h-9 w-9 shrink-0 cursor-move items-center justify-center rounded-full border border-base-300 text-base-content/60">
                                    <x-lucide-move class="h-4 w-4" />
                                </div>

                                <div class="flex min-w-0 flex-1 flex-col gap-3">
                                    <div class="min-w-0">
                                        <p class="wrap-break-word text-sm font-semibold text-base-content">
                                            {{ $module->title }}
                                        </p>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                                        <span class="badge badge-sm badge-outline gap-1.5 min-h-7 px-2.5 text-[11px] font-medium">
                                            <x-dynamic-component
                                                :component="$moduleTypeIcons[$module->type] ?? 'lucide-shapes'"
                                                class="h-3 w-3"
                                            />
                                            <span>{{ $moduleTypeLabels[$module->type] ?? $module->type }}</span>
                                        </span>
                                        <span class="badge badge-sm min-h-7 px-2.5 text-[11px] font-medium">
                                            {{ $moduleStatusLabels[$module->status] ?? $module->status }}
                                        </span>
                                        @php
                                            $moduleValidator = app(\App\Services\ModuleValidation\ModuleValidatorService::class);
                                            $moduleIsValid = $moduleValidator->validate($module);
                                        @endphp
                                        @if ($moduleIsValid)
                                            <span class="badge badge-sm badge-success min-h-7 px-2.5 text-[11px] font-medium">{{ __('Valido') }}</span>
                                        @else
                                            <span class="badge badge-sm badge-error min-h-7 px-2.5 text-[11px] font-medium">{{ __('Non valido') }}</span>
                                        @endif
                                    </div>

                                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                        <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-secondary btn-sm w-full whitespace-nowrap sm:w-auto">
                                            <x-lucide-pencil class="h-4 w-4" />
                                            {{ __('Edit') }}
                                        </a>
                                        <button
                                            type="button"
                                            class="btn btn-accent btn-sm w-full whitespace-nowrap sm:w-auto"
                                            data-open-delete-module-modal
                                            data-modal-target="#delete-module-modal-{{ $module->id }}"
                                        >
                                            <x-lucide-trash-2 class="h-4 w-4" />
                                            {{ __('Delete') }}
                                        </button>
                                    </div>
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

                                        <button type="submit" class="btn btn-accent" data-modal-submit-loading data-loading-text="{{ __('Eliminazione...') }}">
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
                                @foreach ($creatableModuleTypeLabels as $moduleType => $moduleTypeLabel)
                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="type"
                                            value="{{ $moduleType }}"
                                            class="peer sr-only"
                                            @checked(old('type') === $moduleType)
                                        >
                                        <span class="flex min-h-24 items-center rounded-box border border-base-300 bg-base-100 px-4 py-3 text-sm font-medium transition hover:border-primary/40 peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary">
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
                                required
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
                            <button type="submit" class="btn btn-primary" data-modal-submit-loading data-loading-text="{{ __('Salvataggio...') }}">
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

        </div>
    </div>
</div>