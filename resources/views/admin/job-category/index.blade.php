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
            :title="__('Categorie di lavoro')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.job-categories.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$categories"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :search-placeholder="__('Cerca nelle categorie')"
            :empty-message="__('Nessuna categoria disponibile.')"
        >
            @foreach ($categories as $category)
                <tr
                    class="cursor-pointer hover"
                    onclick="window.location='{{ route('admin.job-categories.edit', $category) }}'"
                >
                    <td>{{ $category->id }}</td>
                    <td>{{ $category->name }}</td>
                    <td>{{ $category->code }}</td>
                    <td>
                        <span class="badge {{ $category->is_active ? 'badge-success' : 'badge-ghost' }}">
                            {{ $category->is_active ? __('Attivo') : 'Inattivo' }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
</x-layouts.admin>
