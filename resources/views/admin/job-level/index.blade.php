<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
            ['key' => 'name', 'label' => __('Nome'), 'sortable' => true],
            ['key' => 'code', 'label' => __('Codice'), 'sortable' => true],
            ['key' => 'is_active', 'label' => __('Attivo'), 'sortable' => true],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Livelli di inquadramento')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.job-levels.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$levels"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :search-placeholder="__('Cerca nei livelli')"
            :empty-message="__('Nessun livello disponibile.')"
        >
            @foreach ($levels as $level)
                <tr
                    class="cursor-pointer hover"
                    onclick="window.location='{{ route('admin.job-levels.edit', $level) }}'"
                >
                    <td>{{ $level->id }}</td>
                    <td>{{ $level->name }}</td>
                    <td>{{ $level->code }}</td>
                    <td>
                        <span class="badge {{ $level->is_active ? 'badge-success' : 'badge-ghost' }}">
                            {{ $level->is_active ? __('Attivo') : 'Inattivo' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
</x-layouts.admin>
