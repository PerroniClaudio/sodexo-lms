<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuovo percorso formativo')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.training-paths.store') }}" class="flex flex-col gap-6">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="form-control flex flex-col gap-2 md:col-span-2">
                            <label for="title" class="label p-0">
                                <span class="label-text font-medium">{{ __('Titolo del percorso formativo') }}</span>
                            </label>
                            <input
                                id="title"
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

                        <div class="form-control flex flex-col gap-2 md:col-span-2">
                            <label for="code" class="label p-0">
                                <span class="label-text font-medium">{{ __('Codice percorso formativo') }}</span>
                            </label>
                            <input
                                id="code"
                                name="code"
                                type="text"
                                value="{{ old('code') }}"
                                placeholder="PATH-{id}"
                                class="input input-bordered w-full @error('code') input-error @enderror"
                            >
                            <p class="text-sm text-base-content/70">{{ __('Se lasci vuoto, il codice verrà impostato automaticamente dopo il salvataggio.') }}</p>
                            @error('code')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva e continua') }}</span>
                            <x-lucide-arrow-right class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
