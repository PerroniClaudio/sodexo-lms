<x-layouts.user>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="$course->title">
            <x-slot:actions>
                <a href="{{ route('tutor.courses.show', $course) }}" class="btn btn-ghost">
                    {{ __('Torna al corso') }}
                </a>
            </x-slot:actions>

            {{ __('Registro presenze utenti') }}
        </x-page-header>

        <div
            class="card border border-base-300 bg-base-100 shadow-sm"
            data-tutor-attendance-page
            data-qr-scan-url="{{ route('tutor.courses.attendance.scan', $course) }}"
            data-csrf-token="{{ csrf_token() }}"
        >
            <div class="card-body gap-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="card-title">{{ __('Registra presenze utenti') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Seleziona entrata o uscita per ogni utente iscritto al corso.') }}
                        </p>
                    </div>

                    <button type="button" class="btn btn-primary w-full sm:w-auto" data-open-qr-scan-modal>
                        {{ __('Registra presenza con QR') }}
                    </button>
                </div>

                @if ($enrollments->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-sm text-base-content/70">
                        {{ __('Non ci sono utenti iscritti a questo corso.') }}
                    </div>
                @else
                    <div class="overflow-x-auto rounded-box border border-base-300">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('Nome') }}</th>
                                    <th>{{ __('Cognome') }}</th>
                                    <th class="w-40">{{ __('Entrata') }}</th>
                                    <th class="w-40">{{ __('Uscita') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($enrollments as $enrollment)
                                    <tr>
                                        <td>{{ $enrollment->user?->name ?? __('Non disponibile') }}</td>
                                        <td>{{ $enrollment->user?->surname ?? __('Non disponibile') }}</td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-sm w-full" onclick="document.getElementById('attendance-confirm-entry-{{ $enrollment->getKey() }}').showModal()">
                                                {{ __('Entrata') }}
                                            </button>

                                            <dialog id="attendance-confirm-entry-{{ $enrollment->getKey() }}" class="modal">
                                                <div class="modal-box max-w-lg">
                                                    <h3 class="text-lg font-semibold text-base-content">{{ __('Conferma registrazione entrata') }}</h3>
                                                    <p class="mt-3 text-sm leading-6 text-base-content/70">
                                                        {{ __('Prima di registrare l\'entrata, verifica tramite un documento l\'identità del partecipante.') }}
                                                    </p>
                                                    <p class="mt-2 text-sm font-medium text-base-content">
                                                        {{ trim(($enrollment->user?->surname ?? '').' '.($enrollment->user?->name ?? '')) }}
                                                    </p>

                                                    <div class="modal-action">
                                                        <form method="dialog">
                                                            <button class="btn btn-ghost">{{ __('Annulla') }}</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('tutor.courses.attendance.store', [$course, $enrollment]) }}">
                                                            @csrf
                                                            <input type="hidden" name="type" value="entry">
                                                            <button type="submit" class="btn btn-primary" data-modal-submit-loading data-loading-text="{{ __('Salvataggio...') }}">
                                                                {{ __('Conferma') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <form method="dialog" class="modal-backdrop">
                                                    <button>{{ __('Chiudi') }}</button>
                                                </form>
                                            </dialog>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline btn-primary btn-sm w-full" onclick="document.getElementById('attendance-confirm-exit-{{ $enrollment->getKey() }}').showModal()">
                                                {{ __('Uscita') }}
                                            </button>

                                            <dialog id="attendance-confirm-exit-{{ $enrollment->getKey() }}" class="modal">
                                                <div class="modal-box max-w-lg">
                                                    <h3 class="text-lg font-semibold text-base-content">{{ __('Conferma registrazione uscita') }}</h3>
                                                    <p class="mt-3 text-sm leading-6 text-base-content/70">
                                                        {{ __('Prima di registrare l\'uscita, verifica tramite un documento l\'identità del partecipante.') }}
                                                    </p>
                                                    <p class="mt-2 text-sm font-medium text-base-content">
                                                        {{ trim(($enrollment->user?->surname ?? '').' '.($enrollment->user?->name ?? '')) }}
                                                    </p>

                                                    <div class="modal-action">
                                                        <form method="dialog">
                                                            <button class="btn btn-ghost">{{ __('Annulla') }}</button>
                                                        </form>
                                                        <form method="POST" action="{{ route('tutor.courses.attendance.store', [$course, $enrollment]) }}">
                                                            @csrf
                                                            <input type="hidden" name="type" value="exit">
                                                            <button type="submit" class="btn btn-primary" data-modal-submit-loading data-loading-text="{{ __('Salvataggio...') }}">
                                                                {{ __('Conferma') }}
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <form method="dialog" class="modal-backdrop">
                                                    <button>{{ __('Chiudi') }}</button>
                                                </form>
                                            </dialog>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <dialog class="modal" data-qr-scan-modal>
            <div class="modal-box max-w-xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-base-content">{{ __('Registra presenza con QR') }}</h3>
                        <p class="mt-2 text-sm text-base-content/70">
                            {{ __('Inquadra il QR code mostrato dall\'utente per registrare automaticamente entrata o uscita.') }}
                        </p>
                    </div>

                    <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-qr-scan-modal aria-label="{{ __('Chiudi') }}">
                        ✕
                    </button>
                </div>

                <div class="mt-6 overflow-hidden rounded-box border border-base-300 bg-base-200/40">
                    <video class="aspect-video w-full bg-black object-cover" autoplay muted playsinline data-qr-scan-video></video>
                </div>

                <div class="mt-4 rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70" data-qr-scan-status>
                    {{ __('Apri la telecamera per iniziare la scansione.') }}
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" data-close-qr-scan-modal>
                        {{ __('Chiudi') }}
                    </button>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>{{ __('Chiudi') }}</button>
            </form>
        </dialog>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="card-title">{{ __('Presenze registrate') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Elenco delle presenze già registrate per questo corso.') }}
                        </p>
                    </div>

                    <form method="GET" action="{{ route('tutor.courses.attendance.index', $course) }}" class="flex w-full flex-col gap-3 sm:flex-row lg:w-auto lg:min-w-[24rem]">
                        <label class="form-control w-full">
                            <span class="label-text text-sm font-medium text-base-content/70">{{ __('Utente') }}</span>
                            <select name="attendance_user_id" class="select select-bordered w-full">
                                <option value="">{{ __('Tutti gli utenti') }}</option>
                                @foreach ($attendanceUserOptions as $attendanceUserOption)
                                    <option value="{{ $attendanceUserOption['id'] }}" @selected($selectedAttendanceUserId === $attendanceUserOption['id'])>
                                        {{ $attendanceUserOption['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <div class="flex gap-2 sm:self-end">
                            <button type="submit" class="btn btn-primary flex-1 sm:flex-none">
                                {{ __('Filtra') }}
                            </button>
                            @if ($selectedAttendanceUserId !== null)
                                <a href="{{ route('tutor.courses.attendance.index', $course) }}" class="btn btn-ghost flex-1 sm:flex-none">
                                    {{ __('Reset') }}
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                @if ($attendanceRecords->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-sm text-base-content/70">
                        {{ __('Nessuna presenza registrata fino a questo momento.') }}
                    </div>
                @else
                    <div class="overflow-x-auto rounded-box border border-base-300">
                        <table class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>{{ __('Nome') }}</th>
                                    <th>{{ __('Cognome') }}</th>
                                    <th>{{ __('Presenza') }}</th>
                                    <th>{{ __('Registrata il') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendanceRecords as $attendanceRecord)
                                    <tr>
                                        <td>{{ $attendanceRecord['name'] }}</td>
                                        <td>{{ $attendanceRecord['surname'] }}</td>
                                        <td>{{ $attendanceRecord['type'] }}</td>
                                        <td>{{ $attendanceRecord['recorded_at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @vite('resources/js/pages/tutor-course-attendance.js')
</x-layouts.user>