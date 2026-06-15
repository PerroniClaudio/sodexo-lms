<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Partner')">
            <x-slot:actions>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('create-partner-modal').showModal()">
                    {{ __('Nuovo Partner') }}
                </button>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="GET" action="{{ route('admin.partners.index') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-end">
                    <div class="flex w-full max-w-xl items-center gap-2">
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                name="search"
                                class="grow"
                                value="{{ $tableSearch }}"
                                placeholder="{{ __('Cerca ragione sociale') }}"
                            >
                        </label>
                        <button type="submit" class="btn btn-primary">{{ __('Cerca') }}</button>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Ragione sociale') }}</th>
                                <th>{{ __('Corsi') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($partners as $partner)
                                <tr>
                                    <td>{{ $partner->getKey() }}</td>
                                    <td class="font-medium">{{ $partner->ragione_sociale }}</td>
                                    <td>{{ $partner->courses_count }}</td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.partners.edit', $partner) }}" class="btn btn-primary btn-sm">
                                                {{ __('Modifica') }}
                                            </a>
                                            <form method="POST" action="{{ route('admin.partners.destroy', $partner) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questo partner?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-error btn-sm">{{ __('Elimina') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-base-content/60">{{ __('Nessun partner trovato.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $partners->links() }}
            </div>
        </div>
    </div>

    <dialog id="create-partner-modal" class="modal" @if($errors->has('ragione_sociale')) open @endif>
        <div class="modal-box max-w-xl">
            <h2 class="text-lg font-semibold">{{ __('Crea partner') }}</h2>
            <form method="POST" action="{{ route('admin.partners.store') }}" class="mt-6 flex flex-col gap-4">
                @csrf
                <div class="form-control flex flex-col gap-2">
                    <label for="ragione_sociale" class="label p-0">
                        <span class="label-text font-medium">{{ __('Ragione sociale') }}</span>
                    </label>
                    <input id="ragione_sociale" name="ragione_sociale" type="text" value="{{ old('ragione_sociale') }}" class="input input-bordered w-full @error('ragione_sociale') input-error @enderror" required>
                    @error('ragione_sociale')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('create-partner-modal').close()">{{ __('Annulla') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Salva') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Chiudi') }}</button>
        </form>
    </dialog>
</x-layouts.admin>
