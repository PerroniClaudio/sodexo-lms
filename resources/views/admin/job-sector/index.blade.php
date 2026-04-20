<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
            ['key' => 'name', 'label' => __('Nome'), 'sortable' => true],
            ['key' => 'code', 'label' => __('Codice'), 'sortable' => true],
            ['key' => 'status', 'label' => __('Stato'), 'sortable' => true],
            ['key' => 'actions', 'label' => __('Azioni'), 'sortable' => false],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Settori')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.job-sectors.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$sectors"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :search-placeholder="__('Cerca nei settori')"
            :empty-message="__('Nessun settore disponibile.')"
            :show-search="false"
        >
            <x-slot:filters>
                <form method="GET" action="{{ route('admin.job-sectors.index') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input
                                type="checkbox"
                                name="show_trashed"
                                value="1"
                                class="checkbox"
                                @checked($showTrashed)
                                onchange="handleShowTrashedChange(this)"
                            >
                            <span class="label-text">{{ __('Mostra eliminati') }}</span>
                        </label>
                    </div>

                    <div class="flex w-full max-w-md items-center gap-2">
                        @foreach (request()->query() as $key => $value)
                            @continue(in_array($key, ['search', 'page'], true))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                name="search"
                                value="{{ $tableSearch }}"
                                class="grow"
                                placeholder="{{ __('Cerca nei settori') }}"
                            >
                        </label>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Cerca') }}
                        </button>
                    </div>
                </form>
            </x-slot:filters>
            @foreach ($sectors as $sector)
                <tr class="hover:bg-base-200">
                    <td>{{ $sector->id }}</td>
                    <td>{{ $sector->name }}</td>
                    <td>{{ $sector->code }}</td>
                    <td>
                        @if($sector->trashed())
                            <span class="badge badge-outline badge-error">{{ __('Eliminato') }}</span>
                        @else
                            <span class="badge badge-outline badge-success">{{ __('Attivo') }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-2">
                            @if(!$sector->trashed())
                                <a href="{{ route('admin.job-sectors.edit', $sector) }}" class="btn btn-primary btn-sm">
                                    {{ __('Modifica') }}
                                </a>
                                <form method="POST" action="{{ route('admin.job-sectors.destroy', $sector) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-error btn-sm" 
                                            onclick="return confirm('{{ __('Sei sicuro di voler eliminare questo settore?') }}')">
                                        {{ __('Elimina') }}
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.job-sectors.restore', $sector->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        {{ __('Ripristina') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <script>
        function handleShowTrashedChange(checkbox) {
            const form = checkbox.form;
            const url = new URL(form.action);
            
            // Rimuovi i parametri esistenti
            url.searchParams.delete('show_trashed');
            url.searchParams.delete('page'); // Reset pagina quando cambi filtro
            
            // Aggiungi il nuovo valore
            if (checkbox.checked) {
                url.searchParams.set('show_trashed', '1');
            } else {
                url.searchParams.set('show_trashed', '0');
            }
            
            // Reindirizza alla nuova URL
            window.location.href = url.toString();
        }

        // Reset pagina quando si cerca
        document.addEventListener('DOMContentLoaded', function() {
            const searchForm = document.querySelector('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    const url = new URL(this.action);
                    const formData = new FormData(this);
                    
                    // Rimuovi la pagina dal form per resettarla
                    url.searchParams.delete('page');
                    
                    // Aggiungi i parametri del form
                    for (let [key, value] of formData) {
                        if (key !== 'page') {
                            url.searchParams.set(key, value);
                        }
                    }
                    
                    e.preventDefault();
                    window.location.href = url.toString();
                });
            }
        });
    </script>
</x-layouts.admin>
