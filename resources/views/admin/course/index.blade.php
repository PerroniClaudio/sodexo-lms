<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
            ['key' => 'title', 'label' => __('Titolo del corso'), 'sortable' => true],
            ['key' => 'status', 'label' => __('Stato'), 'sortable' => true],
            ['key' => 'year', 'label' => __('Anno del corso'), 'sortable' => true],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Corsi')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.courses.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$courses"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :search-placeholder="__('Cerca nei corsi')"
            :empty-message="__('Nessun corso disponibile.')"
        >
            @foreach ($courses as $course)
                <tr
                    class="cursor-pointer hover"
                    onclick="window.location='{{ route('admin.courses.edit', $course) }}'"
                >
                    <td>{{ $course->id }}</td>
                    <td>{{ $course->title }}</td>
                    <td>
                        <span class="badge badge-ghost">
                            {{ $course->status }}
                        </span>
                    </td>
                    <td>{{ $course->year }}</td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
</x-layouts.admin>
