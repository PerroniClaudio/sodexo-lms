@props([
    'attendanceRows',
    'asyncAttendanceModules',
    'course',
    'courseValidator',
    'resAttendanceModules',
])

@php
    $isAsyncAttendance = $course->type === 'async';
    $attendanceModules = $isAsyncAttendance ? $asyncAttendanceModules : $resAttendanceModules;
    $defaultMinimumAttendancePercentage = old('minimum_attendance_percentage', 90);
    $selectedAttendanceModuleId = old('module_id', $attendanceModules->count() === 1 ? $attendanceModules->first()?->getKey() : null);
    $defaultModule = $attendanceModules->firstWhere('id', (int) $selectedAttendanceModuleId) ?? $attendanceModules->first();
    $defaultEffectiveStartTime = old('effective_start_time', $defaultModule?->appointment_start_time?->format('H:i'));
    $defaultEffectiveEndTime = old('effective_end_time', $defaultModule?->appointment_end_time?->format('H:i'));
@endphp

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Presenti') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Partecipanti con entrata o uscita registrati per questo corso.') }}
                    </p>
                </div>

                <button
                    type="button"
                    class="btn btn-primary"
                    data-open-res-attendance-confirmation-modal
                    @disabled($attendanceRows->isEmpty() || $attendanceModules->isEmpty())
                >
                    <x-lucide-check class="h-4 w-4" />
                    <span>{{ __('Conferma presenti') }}</span>
                </button>
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
                                <th>{{ __('Tempo permanenza') }}</th>
                                <th>{{ __('Completato') }}</th>
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
                                    <td>{{ sprintf('%02d:%02d', $attendanceHours, $attendanceMinutes) }}</td>
                                    <td>
                                        @if ($attendanceRow['completed'])
                                            <span class="badge badge-success text-success-content">{{ __('Sì') }}</span>
                                        @else
                                            <span class="badge badge-ghost">{{ __('No') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <dialog id="confirm-res-attendance-modal" class="modal">
                <div class="modal-box max-w-xl">
                    <div class="space-y-2">
                        <h3 class="text-lg font-semibold">{{ __('Conferma presenti') }}</h3>
                        <p class="text-sm text-base-content/70">
                            {{ $isAsyncAttendance
                                ? __('Imposta la finestra effettiva della diretta e la soglia minima per completare il modulo live selezionato.')
                                : __('Imposta la soglia minima di permanenza per completare il modulo RES selezionato.') }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('admin.courses.attendance.confirm', $course) }}" class="mt-6 grid gap-4">
                        @csrf

                        @if ($attendanceModules->count() === 1)
                            <input type="hidden" name="module_id" value="{{ $attendanceModules->first()->getKey() }}">
                        @else
                            <label class="form-control gap-2">
                                <span class="label-text font-medium">{{ $isAsyncAttendance ? __('Modulo live') : __('Modulo RES') }}</span>
                                <select name="module_id" class="select select-bordered w-full @error('module_id') select-error @enderror" required>
                                    <option value="">{{ __('Seleziona modulo') }}</option>
                                    @foreach ($attendanceModules as $attendanceModule)
                                        <option value="{{ $attendanceModule->getKey() }}" @selected((string) $selectedAttendanceModuleId === (string) $attendanceModule->getKey())>
                                            {{ $attendanceModule->title }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('module_id')
                                    <span class="text-sm text-error">{{ $message }}</span>
                                @enderror
                            </label>
                        @endif

                        @if ($isAsyncAttendance)
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="form-control gap-2">
                                    <span class="label-text font-medium">{{ __('Ora di inizio effettiva') }}</span>
                                    <input
                                        type="time"
                                        name="effective_start_time"
                                        value="{{ $defaultEffectiveStartTime }}"
                                        class="input input-bordered w-full @error('effective_start_time') input-error @enderror"
                                        required
                                    >
                                    @error('effective_start_time')
                                        <span class="text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </label>

                                <label class="form-control gap-2">
                                    <span class="label-text font-medium">{{ __('Ora di fine effettiva') }}</span>
                                    <input
                                        type="time"
                                        name="effective_end_time"
                                        value="{{ $defaultEffectiveEndTime }}"
                                        class="input input-bordered w-full @error('effective_end_time') input-error @enderror"
                                        required
                                    >
                                    @error('effective_end_time')
                                        <span class="text-sm text-error">{{ $message }}</span>
                                    @enderror
                                </label>
                            </div>
                        @endif

                        <label class="form-control gap-2">
                            <span class="label-text font-medium">{{ __('Percentuale minima') }}</span>
                            <input
                                type="number"
                                name="minimum_attendance_percentage"
                                min="1"
                                max="100"
                                step="1"
                                value="{{ $defaultMinimumAttendancePercentage }}"
                                class="input input-bordered w-full @error('minimum_attendance_percentage') input-error @enderror"
                                required
                            >
                            @error('minimum_attendance_percentage')
                                <span class="text-sm text-error">{{ $message }}</span>
                            @enderror
                        </label>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost" data-close-res-attendance-confirmation-modal>
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" class="btn btn-primary" data-modal-submit-loading data-loading-text="{{ __('Salvataggio...') }}">
                                <x-lucide-check class="h-4 w-4" />
                                <span>{{ __('Conferma presenti') }}</span>
                            </button>
                        </div>
                    </form>
                </div>

                <form method="dialog" class="modal-backdrop">
                    <button type="submit">{{ __('Close') }}</button>
                </form>
            </dialog>
        </div>
    </div>
</div>
