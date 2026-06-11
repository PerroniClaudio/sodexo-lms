@props([
    'course',
    'courseValidator',
])

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div>
                <h2 class="card-title">{{ __('Operazioni corso') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Azioni amministrative disponibili per questo corso.') }}
                </p>
            </div>

            <div class="grid gap-3 sm:max-w-sm">
                @can('duplicate courses')
                    <form method="POST" action="{{ route('admin.courses.duplicate', $course) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-outline w-full justify-start">
                            <x-lucide-copy class="h-4 w-4" />
                            <span>{{ __('Duplica corso') }}</span>
                        </button>
                    </form>

                    <button type="button" class="btn btn-secondary btn-outline w-full justify-start" data-open-duplicate-structure-modal>
                        <x-lucide-copy-plus class="h-4 w-4" />
                        <span>{{ __('Duplica struttura') }}</span>
                    </button>
                @endcan

                <button type="button" class="btn btn-accent btn-outline w-full justify-start" data-open-delete-course-modal>
                    <x-lucide-trash-2 class="h-4 w-4" />
                    <span>{{ __('Delete course') }}</span>
                </button>
            </div>
        </div>
    </div>

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

    <dialog id="duplicate-structure-modal" class="modal">
        <div class="modal-box max-w-lg">
            <form method="POST" action="{{ route('admin.courses.duplicate-structure', $course).'?section=operations' }}" class="space-y-6">
                @csrf

                <div class="space-y-2">
                    <h3 class="text-lg font-semibold">{{ __('Duplica struttura') }}</h3>
                    <p class="text-sm text-base-content/70">
                        {{ __('Crea un nuovo corso copiando struttura, moduli e requisiti. La nuova copia partirà dall\'edizione 1.') }}
                    </p>
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="duplicate-structure-new-code" class="label p-0">
                        <span class="label-text font-medium">{{ __('Nuovo codice corso') }}</span>
                    </label>
                    <input
                        id="duplicate-structure-new-code"
                        name="new_code"
                        type="text"
                        value="{{ old('new_code') }}"
                        class="input input-bordered w-full @error('new_code') input-error @enderror"
                        required
                    >
                    @error('new_code')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Duplica struttura') }}
                    </button>
                </div>
            </form>
        </div>

        <form method="dialog" class="modal-backdrop">
            <button type="submit">{{ __('Close') }}</button>
        </form>
    </dialog>
</div>