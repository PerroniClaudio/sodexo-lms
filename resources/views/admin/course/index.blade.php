<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
            ['key' => 'title', 'label' => __('Titolo del corso'), 'sortable' => true],
            ['key' => 'type', 'label' => __('Tipologia'), 'sortable' => false],
            ['key' => 'status', 'label' => __('Stato'), 'sortable' => true],
            ['key' => 'year', 'label' => __('Anno del corso'), 'sortable' => true],
            ['key' => 'actions', 'label' => __('Azioni'), 'sortable' => false],
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
            <x-slot:filters>
                <form method="GET" action="{{ route('admin.courses.index') }}" class="flex items-center justify-end">
                    @foreach (request()->query() as $key => $value)
                        @continue(in_array($key, ['show_trashed', 'page'], true))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach

                    <label class="label cursor-pointer justify-start gap-3 p-0">
                        <input
                            type="checkbox"
                            name="show_trashed"
                            value="1"
                            class="checkbox"
                            @checked($showTrashed)
                            onchange="this.form.submit()"
                        >
                        <span class="label-text">{{ __('Mostra eliminati') }}</span>
                    </label>
                </form>
            </x-slot:filters>

            @foreach ($courses as $course)
                <tr
                    @class([
                        'hover',
                        'cursor-pointer' => ! $course->trashed(),
                        'opacity-70' => $course->trashed(),
                    ])
                    @if (! $course->trashed())
                        onclick="window.location='{{ route('admin.courses.edit', [$course, 'section' => 'details']) }}'"
                    @endif
                >
                    <td>{{ $course->id }}</td>
                    <td>{{ $course->title }}</td>
                    <td>{{ $course->type }}</td>
                    <td>
                        @if ($course->trashed())
                            <span class="badge badge-error badge-soft h-fit">{{ __('Eliminato') }}</span>
                        @else
                            <span class="badge badge-ghost h-fit">{{ $course->status }}</span>
                        @endif
                    </td>
                    <td>{{ $course->year }}</td>
                    <td>
                        @if ($course->trashed())
                            <form method="POST" action="{{ route('admin.courses.restore', $course->id) }}" onclick="event.stopPropagation();">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">
                                    {{ __('Ripristina') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('admin.courses.edit', [$course, 'section' => 'details']) }}" class="btn btn-primary btn-sm" onclick="event.stopPropagation();">
                                {{ __('Modifica') }}
                            </a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
</x-layouts.admin>
