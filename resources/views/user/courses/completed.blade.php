<x-layouts.user>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Corsi completati')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                @if ($completedEnrollments->isEmpty())
                    <div class="py-8 text-center text-base-content/70">
                        {{ __('Non hai ancora completato nessun corso.') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('Corso') }}</th>
                                    <th>{{ __('Completato il') }}</th>
                                    <th class="w-44"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($completedEnrollments as $item)
                                    @php($enrollment = $item['enrollment'])
                                    @php($certificates = $item['certificates'])

                                    <tr>
                                        <td class="font-semibold">{{ $enrollment->course->title }}</td>
                                        <td>{{ $enrollment->completed_at?->format('d/m/Y') ?? '-' }}</td>
                                        <td class="text-right">
                                            @if ($certificates !== [])
                                                <div class="flex justify-end gap-2">
                                                    @foreach ($certificates as $type => $certificate)
                                                        <a
                                                            href="{{ route('user.completed-courses.certificate.download', ['courseEnrollment' => $enrollment, 'type' => $type]) }}"
                                                            class="btn btn-sm {{ $type === \App\Models\CustomCertificate::TYPE_COMPLETION ? 'btn-secondary' : 'btn-primary' }}"
                                                        >
                                                            {{ __('Scarica attestato :type', ['type' => \Illuminate\Support\Str::lower($certificate['label'])]) }}
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-sm text-base-content/60">{{ __('Attestato non disponibile') }}</span>
                                            @endif
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
