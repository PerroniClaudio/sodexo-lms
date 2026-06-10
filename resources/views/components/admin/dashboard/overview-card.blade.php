@props(['overview'])

@php
    $enrollmentStatusLabels = [
        \App\Models\CourseEnrollment::STATUS_ASSIGNED => __('Assegnati'),
        \App\Models\CourseEnrollment::STATUS_IN_PROGRESS => __('In corso'),
        \App\Models\CourseEnrollment::STATUS_COMPLETED => __('Completati'),
        \App\Models\CourseEnrollment::STATUS_EXPIRED => __('Scaduti'),
    ];
@endphp

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-6">
        <div class="flex items-center justify-between gap-4">
            <h2 class="card-title">
                <x-lucide-chart-column class="h-5 w-5" />
                {{ __('Andamento formazione') }}
            </h2>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ($enrollmentStatusLabels as $status => $label)
                <div class="rounded-box bg-base-200 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-base-content/60">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold">{{ $overview['enrollment_statuses'][$status] ?? 0 }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>{{ __('Corso') }}</th>
                        <th class="text-right">{{ __('Iscritti') }}</th>
                        <th class="text-right">{{ __('In corso') }}</th>
                        <th class="text-right">{{ __('Completati') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($overview['top_courses'] as $course)
                        <tr>
                            <td class="font-medium">{{ $course['title'] }}</td>
                            <td class="text-right">{{ $course['total_enrollments'] }}</td>
                            <td class="text-right">{{ $course['in_progress_enrollments'] }}</td>
                            <td class="text-right">{{ $course['completed_enrollments'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-base-content/60">{{ __('Nessun corso disponibile.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
