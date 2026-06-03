<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuova tipologia documento')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.document-types.store') }}" class="flex flex-col gap-6">
                    @csrf

                    <div class="form-control flex flex-col gap-2">
                        <label for="name" class="label p-0">
                            <span class="label-text font-medium">{{ __('Nome') }}</span>
                        </label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" class="input input-bordered w-full @error('name') input-error @enderror" required>
                        @error('name')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label for="description" class="label p-0">
                            <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                        </label>
                        <textarea id="description" name="description" rows="4" class="textarea textarea-bordered w-full @error('description') textarea-error @enderror">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.document-types.index') }}" class="btn btn-ghost">{{ __('Annulla') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Salva e continua') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
