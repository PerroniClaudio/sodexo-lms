<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica tipologia documento')">
            <x-slot:actions>
                @if($documentType->trashed())
                    <form method="POST" action="{{ route('admin.document-types.restore', $documentType->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-success btn-outline">{{ __('Ripristina') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.document-types.destroy', $documentType) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questa tipologia?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error btn-outline">{{ __('Elimina') }}</button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.document-types.update', $documentType) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <div class="form-control flex flex-col gap-2">
                        <label for="name" class="label p-0">
                            <span class="label-text font-medium">{{ __('Nome') }}</span>
                        </label>
                        <input id="name" name="name" type="text" value="{{ old('name', $documentType->name) }}" class="input input-bordered w-full @error('name') input-error @enderror" required>
                        @error('name')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label for="description" class="label p-0">
                            <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                        </label>
                        <textarea id="description" name="description" rows="4" class="textarea textarea-bordered w-full @error('description') textarea-error @enderror">{{ old('description', $documentType->description) }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.document-types.index') }}" class="btn btn-ghost">{{ __('Annulla') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Salva modifiche') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
