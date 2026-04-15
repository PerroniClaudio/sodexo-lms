<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Corsi')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.courses.create')"
        />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Titolo del corso') }}</th>
                                <th>{{ __('Stato') }}</th>
                                <th>{{ __('Anno del corso') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($courses as $course)
                                <tr>
                                    <td>{{ $course->id }}</td>
                                    <td>{{ $course->title }}</td>
                                    <td>
                                        <span class="badge badge-ghost">
                                            {{ $course->status }}
                                        </span>
                                    </td>
                                    <td>{{ $course->year }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-base-content/70">
                                        {{ __('Nessun corso disponibile.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($courses->hasPages())
                    <div class="flex flex-col gap-4 border-t border-base-300 pt-4 lg:flex-row lg:items-center lg:justify-between">
                        <p class="text-sm text-base-content/70">
                            {{ __('Visualizzazione di :from-:to su :total corsi', ['from' => $courses->firstItem(), 'to' => $courses->lastItem(), 'total' => $courses->total()]) }}
                        </p>

                        <div class="join self-start lg:self-auto">
                            <a
                                href="{{ $courses->previousPageUrl() ?? '#' }}"
                                @class([
                                    'join-item btn',
                                    'btn-disabled pointer-events-none' => $courses->onFirstPage(),
                                ])
                            >
                                {{ __('Precedente') }}
                            </a>

                            @foreach ($courses->getUrlRange(1, $courses->lastPage()) as $page => $url)
                                <a
                                    href="{{ $url }}"
                                    @class([
                                        'join-item btn',
                                        'btn-active' => $page === $courses->currentPage(),
                                    ])
                                >
                                    {{ $page }}
                                </a>
                            @endforeach

                            <a
                                href="{{ $courses->nextPageUrl() ?? '#' }}"
                                @class([
                                    'join-item btn',
                                    'btn-disabled pointer-events-none' => ! $courses->hasMorePages(),
                                ])
                            >
                                {{ __('Successiva') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
