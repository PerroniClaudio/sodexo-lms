<x-layouts.user>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('I miei corsi')" />
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                @if($enrollments->isEmpty())
                    <div class="text-center text-base-content/70 py-8">
                        {{ __('Non sei iscritto a nessun corso al momento.') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('Corso') }}</th>
                                    <th>{{ __('Stato') }}</th>
                                    <th>{{ __('Avanzamento') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($enrollments as $enrollment)
                                    <tr>
                                        <td class="font-semibold">{{ $enrollment->course->title }}</td>
                                        <td>
                                            <span class="badge badge-ghost">{{ __($enrollment->status) }}</span>
                                        </td>
                                        <td>
                                            <div class="w-40">
                                                <progress class="progress progress-primary w-full" value="{{ $enrollment->completion_percentage }}" max="100"></progress>
                                                <span class="text-xs">{{ $enrollment->completion_percentage }}%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="{{ route('user.courses.show', $enrollment->course) }}" class="btn btn-sm btn-primary">
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
