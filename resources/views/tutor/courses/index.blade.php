<x-layouts.user>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('I miei corsi tutor')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                @if ($courses->isEmpty())
                    <div class="py-8 text-center text-base-content/70">
                        {{ __('Non hai ancora moduli assegnati in nessun corso.') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('Corso') }}</th>
                                    <th>{{ __('Tipologia') }}</th>
                                    <th>{{ __('Moduli assegnati') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($courses as $course)
                                    <tr>
                                        <td class="font-semibold">{{ $course->title }}</td>
                                        <td>{{ \App\Models\Course::availableTypeLabels()[$course->type] ?? $course->type }}</td>
                                        <td>{{ $course->assigned_modules_count }}</td>
                                        <td>
                                            <a href="{{ route('tutor.courses.show', $course) }}" class="btn btn-sm btn-primary">
                                                {{ __('Dettaglio') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.user>
