<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuova esercitazione')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna al modulo') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Modulo: :module', ['module' => $module->title]) }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.courses.modules.video-exercises.store', [$course, $module]) }}" class="grid gap-4">
                    @csrf

                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('Nome esercitazione') }}</legend>
                        <input type="text" name="title" value="{{ old('title') }}" class="input input-bordered w-full @error('title') input-error @enderror" required autofocus>
                        @error('title')
                            <span class="mt-1 text-sm text-error">{{ $message }}</span>
                        @enderror
                    </fieldset>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <x-lucide-arrow-right class="h-4 w-4" />
                            <span>{{ __('Crea e modifica') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
