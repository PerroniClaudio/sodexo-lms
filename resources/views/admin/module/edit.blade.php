<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica modulo')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna al corso') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Corso: :course. Tipologia: :type.', ['course' => $course->title, 'type' => $moduleTypeLabels[$module->type] ?? $module->type]) }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.courses.modules.update', [$course, $module]) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-6">
                        @if ($requiresManualTitle)
                            <div class="form-control flex flex-col gap-2">
                                <label for="title" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Titolo del modulo') }}</span>
                                </label>
                                <input
                                    id="title"
                                    name="title"
                                    type="text"
                                    value="{{ old('title', $module->title) }}"
                                    class="input input-bordered w-full @error('title') input-error @enderror"
                                    required
                                >
                                @error('title')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div class="form-control flex flex-col gap-2">
                                <label class="label p-0">
                                    <span class="label-text font-medium">{{ __('Titolo del modulo') }}</span>
                                </label>
                                <input
                                    type="text"
                                    value="{{ $module->title }}"
                                    class="input input-bordered w-full"
                                    disabled
                                >
                            </div>
                        @endif

                        <div class="form-control flex flex-col gap-2">
                            <label for="description" class="label p-0">
                                <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                            </label>
                            <textarea
                                id="description"
                                name="description"
                                class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
                                required
                            >{{ old('description', $module->description) }}</textarea>
                            @error('description')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva modulo') }}</span>
                            <x-lucide-save class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
