<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Settori')"
            :route="route('admin.job-sectors.create')"
            :button-text="__('Nuovo settore')"
        />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body">
                <x-data-table
                    :columns="[
                        ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
                        ['key' => 'name', 'label' => __('Nome'), 'sortable' => true],
                        ['key' => 'code', 'label' => __('Codice'), 'sortable' => true],
                        ['key' => 'is_active', 'label' => __('Stato'), 'sortable' => true],
                    ]"
                    :rows="$sectors"
                    :search-route="route('admin.job-sectors.index')"
                    :search-placeholder="__('Cerca nei settori')"
                    :empty-message="__('Nessun settore disponibile.')"
                >
                    @foreach ($sectors as $sector)
                        <tr class="hover">
                            <td>{{ $sector->id }}</td>
                            <td>{{ $sector->name }}</td>
                            <td>{{ $sector->code ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $sector->is_active ? 'badge-success' : 'badge-ghost' }}">
                                    {{ $sector->is_active ? __('Attivo') : __('Inattivo') }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.job-sectors.edit', $sector) }}" class="btn btn-ghost btn-sm">
                                    {{ __('Modifica') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </x-data-table>
            </div>
        </div>
    </div>
</x-layouts.admin>
