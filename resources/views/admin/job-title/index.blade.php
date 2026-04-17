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
            :title="__('Mansioni')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.job-titles.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$titles"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :search-placeholder="__('Cerca nelle mansioni')"
            :empty-message="__('Nessuna mansione disponibile.')"
        >
            @foreach ($titles as $title)
                <tr
                    class="cursor-pointer hover"
                    onclick="window.location='{{ route('admin.job-titles.edit', $title) }}'"
                >
                    <td>{{ $title->id }}</td>
                    <td>{{ $title->name }}</td>
                    <td>{{ $title->code }}</td>
                    <td>
                        <span class="badge {{ $title->is_active ? 'badge-success' : 'badge-ghost' }}">
                            {{ $title->is_active ? __('Attivo') : 'Inattivo' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
</x-layouts.admin>
