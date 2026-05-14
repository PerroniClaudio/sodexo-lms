<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h3 class="text-base font-semibold text-base-content">{{ __('Docenti assegnati') }}</h3>
                @if ($module->type === 'live')
                    <p class="text-sm text-base-content/70">
                        {{ __('I docenti assegnati potranno accedere e trasmettere le dirette.') }}
                    </p>
                @endif
            </div>

            <button
                type="button"
                class="btn btn-secondary"
                data-open-teacher-assignment-modal
                @disabled($availableTeachers->isEmpty())
            >
                <x-lucide-user-plus class="h-4 w-4" />
                <span>{{ __('Aggiungi docenti') }}</span>
            </button>
        </div>

        @if ($assignedTeachers->isEmpty())
            <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/70">
                {{ __('Nessun docente assegnato a questo modulo.') }}
            </div>
        @else
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($assignedTeachers as $teacherEnrollment)
                    <div class="rounded-box border border-base-300 bg-base-100 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-base-content">
                                    {{ $teacherEnrollment->user?->full_name ?? __('Docente non disponibile') }}
                                </p>
                                <p class="mt-1 text-sm text-base-content/70">
                                    {{ $teacherEnrollment->user?->email }}
                                </p>
                                <p class="mt-2 text-xs uppercase tracking-wide text-base-content/50">
                                    {{ __('Assegnato il :date', ['date' => $teacherEnrollment->assigned_at?->format('d/m/Y H:i')]) }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="btn btn-error btn-xs"
                                data-open-staff-removal-modal
                                data-modal-target="#remove-teacher-modal-{{ $teacherEnrollment->getKey() }}"
                            >
                                {{ __('Rimuovi') }}
                            </button>
                        </div>
                    </div>

                    <dialog id="remove-teacher-modal-{{ $teacherEnrollment->getKey() }}" class="modal">
                        <div class="modal-box max-w-lg">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold">{{ __('Conferma rimozione docente') }}</h3>
                                <p class="text-sm text-base-content/70">
                                    {{ __('Vuoi rimuovere :teacher dai docenti assegnati a questo modulo?', ['teacher' => $teacherEnrollment->user?->full_name ?? __('questo docente')]) }}
                                </p>
                            </div>

                            <div class="modal-action mt-6">
                                <form method="dialog">
                                    <button type="submit" class="btn btn-ghost">
                                        {{ __('Annulla') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.courses.modules.teachers.destroy', [$course, $module, $teacherEnrollment]) }}">
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

        @if ($availableTeachers->isEmpty())
            <p class="text-sm text-base-content/70">
                {{ __('Tutti i docenti disponibili sono già assegnati a questo modulo.') }}
            </p>
        @endif

        <dialog id="assign-teachers-modal" class="modal">
            <div class="modal-box max-w-2xl">
                <div class="space-y-2">
                    <h3 class="text-lg font-semibold">{{ __('Aggiungi docenti al modulo') }}</h3>
                    <p class="text-sm text-base-content/70">
                        {{ __('Seleziona uno o più docenti da assegnare a questo modulo.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.courses.modules.teachers.assign', [$course, $module]) }}" class="mt-6 space-y-6">
                    @csrf

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-medium text-base-content">{{ __('Docenti disponibili') }}</legend>

                        <div class="max-h-80 space-y-3 overflow-y-auto pr-1">
                            @foreach ($availableTeachers as $teacher)
                                <label class="flex cursor-pointer items-start gap-3 rounded-box border border-base-300 bg-base-100 p-4 transition hover:border-primary/40 hover:bg-primary/5">
                                    <input
                                        type="checkbox"
                                        name="teacher_ids[]"
                                        value="{{ $teacher->getKey() }}"
                                        class="checkbox checkbox-sm mt-0.5"
                                        @checked(collect(old('teacher_ids', []))->contains($teacher->getKey()))
                                    >
                                    <span class="flex flex-col">
                                        <span class="font-medium text-base-content">{{ $teacher->full_name }}</span>
                                        <span class="text-sm text-base-content/70">{{ $teacher->email }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('teacher_ids')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror

                        @error('teacher_ids.*')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="modal-action mt-0">
                        <button
                            type="button"
                            class="btn btn-ghost"
                            data-close-teacher-assignment-modal
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
