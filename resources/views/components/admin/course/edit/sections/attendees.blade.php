@props([
    'attendanceRows',
    'course',
    'courseValidator',
])

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div>
                <h2 class="card-title">{{ __('Presenti') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Partecipanti con record di entrata o uscita registrati per questo corso.') }}
                </p>
            </div>

            @if ($attendanceRows->isEmpty())
                <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70">
                    {{ __('Nessun record di presenza presente per questo corso.') }}
                </div>
            @else
                <div class="overflow-x-auto rounded-box border border-base-300">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>{{ __('Partecipante') }}</th>
                                <th>{{ __('Email') }}</th>
                                <th>{{ __('Record') }}</th>
                                <th>{{ __('Tempo permanenza') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attendanceRows as $attendanceRow)
                                @php
                                    $attendanceHours = intdiv($attendanceRow['attendance_seconds'], 3600);
                                    $attendanceMinutes = intdiv($attendanceRow['attendance_seconds'] % 3600, 60);
                                @endphp
                                <tr>
                                    <td class="font-medium">{{ $attendanceRow['user'] }}</td>
                                    <td>{{ $attendanceRow['email'] }}</td>
                                    <td>{{ $attendanceRow['records_count'] }}</td>
                                    <td>{{ sprintf('%02d:%02d', $attendanceHours, $attendanceMinutes) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
