@php
    $defaultEffectiveStartTime = old('effective_start_time', $module->appointment_start_time?->format('H:i'));
    $defaultEffectiveEndTime = old('effective_end_time', $module->appointment_end_time?->format('H:i'));
    $defaultMinimumAttendancePercentage = old('minimum_attendance_percentage');
@endphp

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h3 class="text-base font-semibold text-base-content">{{ __('Partecipazione alla live') }}</h3>
                <p class="text-sm text-base-content/70">
                    {{ __('Controlla i partecipanti registrati e conferma chi ha seguito abbastanza minuti per poter proseguire nel corso.') }}
                </p>
            </div>

            <button
                type="button"
                class="btn btn-primary"
                data-open-attendance-confirmation-modal
                @disabled($liveAttendanceRows->isEmpty())
            >
                <x-lucide-check class="h-4 w-4" />
                <span>{{ __('Conferma partecipanti') }}</span>
            </button>
        </div>

        @if ($liveAttendanceRows->isEmpty())
            <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/70">
                {{ __('Nessuna partecipazione registrata per questo modulo live.') }}
            </div>
        @else
            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Utente') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Tempo registrato') }}</th>
                            <th>{{ __('Stato modulo') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($liveAttendanceRows as $attendanceRow)
                            @php
                                $attendanceHours = intdiv($attendanceRow['attendance_seconds'], 3600);
                                $attendanceMinutes = intdiv($attendanceRow['attendance_seconds'] % 3600, 60);
                                $progressStatus = $attendanceRow['progress']?->status;
                                $progressLabel = $moduleProgressStatusLabels[$progressStatus] ?? __('Non disponibile');
                            @endphp

                            <tr>
                                <td class="font-medium text-base-content">
                                    {{ $attendanceRow['user']?->full_name ?? __('Utente non disponibile') }}
                                </td>
                                <td class="text-sm text-base-content/70">
                                    {{ $attendanceRow['user']?->email ?? '—' }}
                                </td>
                                <td>
                                    {{ sprintf('%02d:%02d', $attendanceHours, $attendanceMinutes) }}
                                </td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="badge badge-outline">{{ $progressLabel }}</span>

                                        @unless ($attendanceRow['is_current_module'])
                                            <span class="badge badge-ghost">{{ __('Modulo non corrente') }}</span>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <dialog id="confirm-attendance-modal" class="modal">
            <div class="modal-box max-w-3xl">
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold">{{ __('Conferma partecipanti live') }}</h3>
                    <p class="text-sm text-base-content/70">
                        {{ __('Inserisci la finestra effettiva della live e la soglia minima di frequenza per sbloccare il modulo successivo agli utenti idonei.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.courses.modules.attendance.confirm', [$course, $module]) }}" class="mt-6 grid gap-4 lg:grid-cols-[repeat(3,minmax(0,1fr))_auto] lg:items-end">
                    @csrf

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

                    <label class="form-control gap-2">
                        <span class="label-text font-medium">{{ __('Percentuale minima') }}</span>
                        <input
                            type="number"
                            name="minimum_attendance_percentage"
                            min="1"
                            max="100"
                            step="1"
                            value="{{ $defaultMinimumAttendancePercentage }}"
                            placeholder="80"
                            class="input input-bordered w-full @error('minimum_attendance_percentage') input-error @enderror"
                            required
                        >
                        @error('minimum_attendance_percentage')
                            <span class="text-sm text-error">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="flex flex-col gap-2 lg:items-end">
                        <button type="submit" class="btn btn-primary w-full lg:w-auto">
                            <x-lucide-check class="h-4 w-4" />
                            <span>{{ __('Conferma presenti') }}</span>
                        </button>
                        <button
                            type="button"
                            class="btn btn-ghost w-full lg:w-auto"
                            data-close-attendance-confirmation-modal
                        >
                            {{ __('Cancel') }}
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
