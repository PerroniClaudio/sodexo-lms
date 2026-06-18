<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8" data-course-class-attendance-page>
        <x-page-header :title="__('Gestisci presenze')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.classes.edit', [$course, $courseClass]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Indietro') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Classe: :class. Modulo: :module.', ['class' => $courseClass->name, 'module' => $module->title]) }}
        </x-page-header>

        @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <h2 class="card-title text-base">{{ __('Presenze registrate') }}</h2>

                <div class="overflow-x-auto rounded-box border border-base-300">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>{{ __('Nome') }}</th>
                                <th>{{ __('Cognome') }}</th>
                                <th>{{ __('Codice fiscale') }}</th>
                                <th>{{ __('Entrata') }}</th>
                                <th>{{ __('Uscita') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($existingAttendanceRows as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>{{ $row['surname'] }}</td>
                                    <td>{{ $row['fiscal_code'] }}</td>
                                    <td>{{ $row['entry'] ? \Illuminate\Support\Carbon::parse($row['entry'])->format('d/m/Y H:i') : '—' }}</td>
                                    <td>{{ $row['exit'] ? \Illuminate\Support\Carbon::parse($row['exit'])->format('d/m/Y H:i') : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-sm text-base-content/60">{{ __('Nessuna presenza già creata.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <form method="POST" action="{{ route('admin.courses.classes.attendance.store', [$course, $courseClass]) }}" enctype="multipart/form-data" class="card border border-base-300 bg-base-100 shadow-sm">
            @csrf

            <div class="card-body gap-4">
                <h2 class="card-title text-base">{{ __('Partecipanti') }}</h2>

                <div class="overflow-x-auto rounded-box border border-base-300" data-course-class-attendance>
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th>{{ __('Nome') }}</th>
                                <th>{{ __('Cognome') }}</th>
                                <th>{{ __('Codice fiscale') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr>
                                    <td class="align-top">{{ $assignment->user?->name }}</td>
                                    <td class="align-top">{{ $assignment->user?->surname }}</td>
                                    <td class="align-top">{{ $assignment->user?->fiscal_code }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-8 text-center text-sm text-base-content/60">{{ __('Tutte le presenze sono già state create.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <section class="rounded-box border border-base-300 bg-base-200/30 p-4">
                    <div class="grid gap-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-base-content">{{ __('Operazioni Excel') }}</h3>
                                <p class="text-sm text-base-content/60">{{ __('Scarica il template e importa il foglio compilato.') }}</p>
                            </div>
                            <a href="{{ route('admin.courses.classes.attendance.template', [$course, $courseClass]) }}" class="btn btn-outline">
                                <x-lucide-download class="h-4 w-4" />
                                <span>{{ __('Scarica template') }}</span>
                            </a>
                        </div>

                        <div class="grid max-w-xl gap-3">
                            <label class="form-control">
                                <span class="label-text mb-1">{{ __('File presenze Excel') }}</span>
                                <input type="file" name="attendance_file" accept=".xlsx,.xls" class="file-input file-input-bordered w-full" @required($assignments->isNotEmpty())>
                            </label>
                            @error('attendance_file')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="btn btn-primary justify-self-start" @disabled($assignments->isEmpty())>
                                <x-lucide-upload class="h-4 w-4" />
                                <span>{{ __('Importa presenze') }}</span>
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </form>

        <section class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <div>
                    <h2 class="card-title text-base">{{ __('Registro presenze cartaceo') }}</h2>
                    @if ($attendanceRegisterFile)
                        <a href="{{ route('admin.courses.classes.attendance.register.download', [$course, $courseClass]) }}" class="link link-primary text-sm">
                            {{ $attendanceRegisterFile->original_name }}
                        </a>
                    @else
                        <p class="text-sm text-base-content/60">{{ __('Nessun registro caricato.') }}</p>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.courses.classes.attendance.register.store', [$course, $courseClass]) }}" enctype="multipart/form-data" class="grid max-w-xl gap-3">
                    @csrf
                    <label class="form-control">
                        <span class="label-text mb-1">{{ __('Scansione registro') }}</span>
                        <input type="file" name="register_file" accept=".pdf,.jpg,.jpeg,.png" class="file-input file-input-bordered w-full" required>
                    </label>
                    <button type="submit" class="btn btn-primary justify-self-start">
                        <x-lucide-upload class="h-4 w-4" />
                        <span>{{ __('Carica registro') }}</span>
                    </button>
                </form>

                @error('register_file')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
            </div>
        </section>
    </div>

    @vite(['resources/js/pages/admin-course-edit.js'])
</x-layouts.admin>
