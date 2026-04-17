<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Ruoli')"
            :route="route('admin.job-roles.create')"
            :button-text="__('Nuovo ruolo')"
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
                    :rows="$roles"
                    :search-route="route('admin.job-roles.index')"
                    :search-placeholder="__('Cerca nei ruoli')"
                    :empty-message="__('Nessun ruolo disponibile.')"
                >
                    @foreach ($roles as $role)
                        <tr class="hover">
                            <td>{{ $role->id }}</td>
                            <td>{{ $role->name }}</td>
                            <td>{{ $role->code ?? '-' }}</td>
                            <td>
                                <span class="badge {{ $role->is_active ? 'badge-success' : 'badge-ghost' }}">
                                    {{ $role->is_active ? __('Attivo') : __('Inattivo') }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.job-roles.edit', $role) }}" class="btn btn-ghost btn-sm">
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
