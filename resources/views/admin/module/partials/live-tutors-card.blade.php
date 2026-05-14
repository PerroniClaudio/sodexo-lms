<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h3 class="text-base font-semibold text-base-content">{{ __('Tutor assegnati') }}</h3>
                @if ($module->type === 'live')
                    <p class="text-sm text-base-content/70">
                        {{ __('I tutor assegnati potranno accedere e moderare le dirette.') }}
                    </p>
                @endif
            </div>

            <button
                type="button"
                class="btn btn-secondary"
                data-open-tutor-assignment-modal
                @disabled($availableTutors->isEmpty())
            >
                <x-lucide-user-plus class="h-4 w-4" />
                <span>{{ __('Aggiungi tutor') }}</span>
            </button>
        </div>

        @if ($assignedTutors->isEmpty())
            <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/70">
                {{ __('Nessun tutor assegnato a questo modulo.') }}
            </div>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($assignedTutors as $tutorEnrollment)
                    <div class="rounded-box border border-base-300 bg-base-100 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-base-content">
                                    {{ $tutorEnrollment->user?->full_name ?? __('Tutor non disponibile') }}
                                </p>
                                <p class="mt-1 text-sm text-base-content/70">
                                    {{ $tutorEnrollment->user?->email }}
                                </p>
                                <p class="mt-2 text-xs uppercase tracking-wide text-base-content/50">
                                    {{ __('Assegnato il :date', ['date' => $tutorEnrollment->assigned_at?->format('d/m/Y H:i')]) }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="btn btn-error btn-xs"
                                data-open-staff-removal-modal
                                data-modal-target="#remove-tutor-modal-{{ $tutorEnrollment->getKey() }}"
                            >
                                {{ __('Rimuovi') }}
                            </button>
                        </div>
                    </div>

                    <dialog id="remove-tutor-modal-{{ $tutorEnrollment->getKey() }}" class="modal">
                        <div class="modal-box max-w-lg">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold">{{ __('Conferma rimozione tutor') }}</h3>
                                <p class="text-sm text-base-content/70">
                                    {{ __('Vuoi rimuovere :tutor dai tutor assegnati a questo modulo?', ['tutor' => $tutorEnrollment->user?->full_name ?? __('questo tutor')]) }}
                                </p>
                            </div>

                            <div class="modal-action mt-6">
                                <form method="dialog">
                                    <button type="submit" class="btn btn-ghost">
                                        {{ __('Annulla') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.courses.modules.tutors.destroy', [$course, $module, $tutorEnrollment]) }}">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-error">
                                        {{ __('Conferma rimozione') }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <form method="dialog" class="modal-backdrop">
                            <button type="submit">{{ __('Close') }}</button>
                        </form>
                    </dialog>
                @endforeach
            </div>
        @endif

        @if ($availableTutors->isEmpty())
            <p class="text-sm text-base-content/70">
                {{ __('Tutti i tutor disponibili sono già assegnati a questo modulo.') }}
            </p>
        @endif

        <dialog id="assign-tutors-modal" class="modal">
            <div class="modal-box max-w-2xl">
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold">{{ __('Aggiungi tutor al modulo') }}</h3>
                    <p class="text-sm text-base-content/70">
                        {{ __('Seleziona uno o più tutor da assegnare a questo modulo.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.courses.modules.tutors.assign', [$course, $module]) }}" class="mt-6 space-y-6">
                    @csrf

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-medium text-base-content">{{ __('Tutor disponibili') }}</legend>

                        <div class="max-h-80 space-y-3 overflow-y-auto pr-1">
                            @foreach ($availableTutors as $tutor)
                                <label class="flex cursor-pointer items-start gap-3 rounded-box border border-base-300 bg-base-100 p-4 transition hover:border-primary/40 hover:bg-primary/5">
                                    <input
                                        type="checkbox"
                                        name="tutor_ids[]"
                                        value="{{ $tutor->getKey() }}"
                                        class="checkbox checkbox-sm mt-0.5"
                                        @checked(collect(old('tutor_ids', []))->contains($tutor->getKey()))
                                    >
                                    <span class="flex flex-col">
                                        <span class="font-medium text-base-content">{{ $tutor->full_name }}</span>
                                        <span class="text-sm text-base-content/70">{{ $tutor->email }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('tutor_ids')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror

                        @error('tutor_ids.*')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="modal-action mt-0">
                        <button
                            type="button"
                            class="btn btn-ghost"
                            data-close-tutor-assignment-modal
                        >
                            {{ __('Cancel') }}
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <x-lucide-check class="h-4 w-4" />
                            <span>{{ __('Conferma') }}</span>
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
