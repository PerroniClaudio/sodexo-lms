<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
            ['key' => 'title', 'label' => __('Titolo del percorso'), 'sortable' => true],
            ['key' => 'code', 'label' => __('Codice'), 'sortable' => true],
            ['key' => 'status', 'label' => __('Stato'), 'sortable' => true],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Percorsi formativi')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.training-paths.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$trainingPaths"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :search-placeholder="__('Cerca nei percorsi formativi')"
            :empty-message="__('Nessun percorso formativo disponibile.')"
        >
            @foreach ($trainingPaths as $trainingPath)
                <tr
                    class="cursor-pointer hover"
                    onclick="window.location='{{ route('admin.training-paths.edit', [$trainingPath, 'section' => 'details']) }}'"
                >
                    <td>{{ $trainingPath->id }}</td>
                    <td>{{ $trainingPath->title }}</td>
                    <td>{{ $trainingPath->code ?: '—' }}</td>
                    <td>
                        <span class="badge badge-ghost h-fit">
                            {{ $trainingPath->status }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
</x-layouts.admin>
