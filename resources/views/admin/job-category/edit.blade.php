<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica categoria di lavoro')">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.job-categories.destroy', $category) }}" onsubmit="return confirm('{{ __('Confirm deletion') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-error btn-outline">
                        <x-lucide-trash-2 class="h-4 w-4" />
                        <span>{{ __('Elimina categoria') }}</span>
                    </button>
                </form>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="card-title">{{ __('Dati anagrafici') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Gestisci le categorie di lavoro.') }}
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.job-categories.update', $category) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="form-control flex flex-col gap-2">
                            <label for="name" class="label p-0">
                                <span class="label-text font-medium">{{ __('Nome') }}</span>
                            </label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name', $category->name) }}"
                                class="input input-bordered w-full @error('name') input-error @enderror"
                                required
                            >
                            @error('name')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-control flex flex-col gap-2">
                            <label for="code" class="label p-0">
                                <span class="label-text font-medium">{{ __('Codice') }}</span>
                            </label>
                            <input
                                id="code"
                                name="code"
                                type="text"
                                value="{{ old('code', $category->code) }}"
                                class="input input-bordered w-full @error('code') input-error @enderror"
                            >
                            @error('code')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label for="description" class="label p-0">
                            <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            class="textarea textarea-bordered w-full @error('description') textarea-error @enderror"
                        >{{ old('description', $category->description) }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                class="checkbox"
                                @checked(old('is_active', $category->is_active))
                            >
                            <span class="label-text font-medium">{{ __('Attivo') }}</span>
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Salva dati') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
