<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Modifica settore')"
            :description="__('Gestisci i settori.')"
        >
            <x-slot:actions>
                @if($sector->trashed())
                    <form method="POST" action="{{ route('admin.job-sectors.restore', $sector->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-outline">
                            <x-lucide-refresh-cw class="h-4 w-4" />
                            <span>{{ __('Ripristina settore') }}</span>
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.job-sectors.destroy', $sector) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questo settore?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error btn-outline">
                            <x-lucide-trash-2 class="h-4 w-4" />
                            <span>{{ __('Elimina settore') }}</span>
                        </button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.job-sectors.update', $sector) }}" class="flex flex-col gap-6">
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
                                value="{{ old('name', $sector->name) }}"
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
                                value="{{ old('code', $sector->code) }}"
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
                        >{{ old('description', $sector->description) }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.job-sectors.index') }}" class="btn btn-ghost">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva modifiche') }}</span>
                            <x-lucide-check class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
