@php
    $appearsAt = gmdate('H:i:s', $videoExercise->appears_at_seconds);
    $minimumTime = sprintf('%02d:%02d', intdiv($videoExercise->minimum_seconds, 3600), intdiv($videoExercise->minimum_seconds % 3600, 60));
@endphp

<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8" data-module-edit-page>
        <x-page-header :title="__('Modifica esercitazione')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna al modulo') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Modulo: :module', ['module' => $module->title]) }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-5">
                <h2 class="text-lg font-semibold">{{ __('Impostazioni') }}</h2>
                <form method="POST" action="{{ route('admin.courses.modules.video-exercises.update', [$course, $module, $videoExercise]) }}" enctype="multipart/form-data" class="grid gap-4">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-4 md:grid-cols-3">
                        <fieldset class="fieldset md:col-span-3">
                            <legend class="fieldset-legend">{{ __('Nome') }}</legend>
                            <input type="text" name="title" value="{{ old('title', $videoExercise->title) }}" class="input input-bordered w-full @error('title') input-error @enderror" required>
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Timestamp apparizione') }}</legend>
                            <input type="text" name="appears_at" value="{{ old('appears_at', $appearsAt) }}" placeholder="00:03:30" pattern="[0-9]{2,}:[0-9]{2}:[0-9]{2}" class="input input-bordered w-full @error('appears_at') input-error @enderror" required>
                            <span class="mt-1 text-xs text-base-content/60">{{ __('Formato hh:mm:ss') }}</span>
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Tempo minimo') }}</legend>
                            <input type="text" name="minimum_time" value="{{ old('minimum_time', $minimumTime) }}" placeholder="00:05" pattern="[0-9]{2,}:[0-9]{2}" class="input input-bordered w-full @error('minimum_time') input-error @enderror" required>
                            <span class="mt-1 text-xs text-base-content/60">{{ __('Formato ore:minuti') }}</span>
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('PDF autovalutazione') }}</legend>
                            <input type="file" name="self_evaluation" accept=".pdf,application/pdf" class="file-input file-input-bordered w-full @error('self_evaluation') file-input-error @enderror">
                        </fieldset>
                    </div>

                    @if ($videoExercise->self_evaluation_path)
                        <div class="alert alert-success">
                            <x-lucide-file-check class="h-5 w-5" />
                            <span>{{ __('Documento attuale: :name', ['name' => $videoExercise->self_evaluation_original_name]) }}</span>
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <x-lucide-save class="h-4 w-4" />
                            <span>{{ __('Salva impostazioni') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ __('Materiale didattico') }}</h2>
                        <p class="text-sm text-base-content/60">{{ __('Aggiungi file, video YouTube o testo libero.') }}</p>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('add-material-modal').showModal()">
                        <x-lucide-plus class="h-4 w-4" />
                        <span>{{ __('Nuovo asset') }}</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Titolo') }}</th>
                                <th>{{ __('Tipo') }}</th>
                                <th>{{ __('Dettaglio') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($videoExercise->materials as $material)
                                <tr>
                                    <td class="align-top font-medium">{{ $material->title }}</td>
                                    <td class="align-top"><span class="badge badge-outline">{{ $material->type }}</span></td>
                                    <td class="max-w-md align-top truncate">
                                        @if ($material->type === 'file')
                                            {{ $material->original_name }}
                                        @elseif ($material->type === 'video')
                                            {{ $material->youtube_url }}
                                        @else
                                            {{ strip_tags($material->content_html) }}
                                        @endif
                                    </td>
                                    <td class="align-top">
                                        <div class="flex justify-end gap-2">
                                            @if ($material->type === 'file')
                                                <a href="{{ route('admin.courses.modules.video-exercises.materials.download', [$course, $module, $videoExercise, $material]) }}" class="btn btn-outline btn-sm">
                                                    <x-lucide-download class="h-4 w-4" />
                                                    <span>{{ __('Scarica') }}</span>
                                                </a>
                                            @endif
                                            @if (in_array($material->type, ['video', 'text'], true))
                                                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit-material-modal-{{ $material->getKey() }}').showModal()">
                                                    <x-lucide-pencil class="h-4 w-4" />
                                                    <span>{{ __('Modifica') }}</span>
                                                </button>
                                            @endif
                                            <form method="POST" action="{{ route('admin.courses.modules.video-exercises.materials.destroy', [$course, $module, $videoExercise, $material]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-error btn-outline btn-sm">
                                                    <x-lucide-trash-2 class="h-4 w-4" />
                                                    <span>{{ __('Elimina') }}</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-sm text-base-content/60">{{ __('Nessun materiale didattico configurato.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @foreach ($videoExercise->materials->whereIn('type', ['video', 'text']) as $material)
            @php($editorTarget = 'material_content_html_'.$material->getKey())
            <dialog id="edit-material-modal-{{ $material->getKey() }}" class="modal">
                <div class="modal-box max-w-2xl">
                    <h3 class="mb-4 text-lg font-bold">{{ __('Modifica asset didattico') }}</h3>
                    <form method="POST" action="{{ route('admin.courses.modules.video-exercises.materials.update', [$course, $module, $videoExercise, $material]) }}" class="grid gap-4">
                        @csrf
                        @method('PUT')
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Titolo') }}</legend>
                            <input type="text" name="title" value="{{ $material->title }}" class="input input-bordered w-full" required>
                        </fieldset>

                        @if ($material->type === 'video')
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">{{ __('URL YouTube') }}</legend>
                                <input type="url" name="youtube_url" value="{{ $material->youtube_url }}" class="input input-bordered w-full" required>
                            </fieldset>
                        @else
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">{{ __('Testo libero') }}</legend>
                                <textarea id="{{ $editorTarget }}" name="content_html" class="hidden">{{ $material->content_html }}</textarea>
                                <div class="rounded border border-base-300 bg-base-100">
                                    <div class="flex flex-wrap gap-1 border-b border-base-300 p-2" data-module-tiptap-toolbar="{{ $editorTarget }}">
                                        <button type="button" class="btn btn-xs btn-ghost" data-command="bold"><x-lucide-bold class="h-3.5 w-3.5" /></button>
                                        <button type="button" class="btn btn-xs btn-ghost" data-command="italic"><x-lucide-italic class="h-3.5 w-3.5" /></button>
                                        <button type="button" class="btn btn-xs btn-ghost" data-command="heading" data-level="2">H2</button>
                                        <button type="button" class="btn btn-xs btn-ghost" data-command="paragraph">P</button>
                                        <button type="button" class="btn btn-xs btn-ghost" data-command="bulletList"><x-lucide-list class="h-3.5 w-3.5" /></button>
                                        <button type="button" class="btn btn-xs btn-ghost" data-command="orderedList"><x-lucide-list-ordered class="h-3.5 w-3.5" /></button>
                                        <button type="button" class="btn btn-xs btn-ghost" data-command="undo"><x-lucide-undo-2 class="h-3.5 w-3.5" /></button>
                                        <button type="button" class="btn btn-xs btn-ghost" data-command="redo"><x-lucide-redo-2 class="h-3.5 w-3.5" /></button>
                                    </div>
                                    <div data-module-tiptap-editor data-target="{{ $editorTarget }}" class="min-h-32 p-3"></div>
                                </div>
                            </fieldset>
                        @endif

                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-material-modal-{{ $material->getKey() }}').close()">{{ __('Annulla') }}</button>
                            <button type="submit" class="btn btn-primary">
                                <x-lucide-save class="h-4 w-4" />
                                <span>{{ __('Salva') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button>{{ __('Chiudi') }}</button>
                </form>
            </dialog>
        @endforeach

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ __('Domande aperte') }}</h2>
                        <p class="text-sm text-base-content/60">{{ __('Configura testo domanda e minimo caratteri.') }}</p>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('add-question-modal').showModal()">
                        <x-lucide-plus class="h-4 w-4" />
                        <span>{{ __('Nuova domanda') }}</span>
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Domanda') }}</th>
                                <th>{{ __('Minimo caratteri') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($videoExercise->questions as $question)
                                <tr>
                                    <td class="max-w-3xl align-top whitespace-pre-wrap">{{ $question->text }}</td>
                                    <td class="align-top">{{ $question->minimum_characters }}</td>
                                    <td class="align-top">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('edit-question-modal-{{ $question->getKey() }}').showModal()">
                                                <x-lucide-pencil class="h-4 w-4" />
                                                <span>{{ __('Modifica') }}</span>
                                            </button>
                                            <form method="POST" action="{{ route('admin.courses.modules.video-exercises.questions.destroy', [$course, $module, $videoExercise, $question]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-error btn-outline btn-sm">
                                                    <x-lucide-trash-2 class="h-4 w-4" />
                                                    <span>{{ __('Elimina') }}</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-8 text-center text-sm text-base-content/60">{{ __('Nessuna domanda configurata.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @foreach ($videoExercise->questions as $question)
            <dialog id="edit-question-modal-{{ $question->getKey() }}" class="modal">
                <div class="modal-box max-w-2xl">
                    <h3 class="mb-4 text-lg font-bold">{{ __('Modifica domanda') }}</h3>
                    <form method="POST" action="{{ route('admin.courses.modules.video-exercises.questions.update', [$course, $module, $videoExercise, $question]) }}" class="grid gap-4">
                        @csrf
                        @method('PUT')
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Domanda') }}</legend>
                            <textarea name="text" class="textarea textarea-bordered min-h-32 w-full" required>{{ $question->text }}</textarea>
                        </fieldset>
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Minimo caratteri') }}</legend>
                            <input type="number" name="minimum_characters" value="{{ $question->minimum_characters }}" min="1" class="input input-bordered w-full" required>
                        </fieldset>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost" onclick="document.getElementById('edit-question-modal-{{ $question->getKey() }}').close()">{{ __('Annulla') }}</button>
                            <button type="submit" class="btn btn-primary">
                                <x-lucide-save class="h-4 w-4" />
                                <span>{{ __('Salva') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button>{{ __('Chiudi') }}</button>
                </form>
            </dialog>
        @endforeach

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-5">
                <div>
                    <h2 class="text-lg font-semibold">{{ __('Esportazioni') }}</h2>
                    <p class="text-sm text-base-content/60">{{ __('Scarica le risposte degli utenti per questa esercitazione.') }}</p>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <a href="{{ route('admin.courses.modules.video-exercises.activity-export', [$course, $module, $videoExercise]) }}" class="btn btn-outline">
                        <x-lucide-download class="h-4 w-4" />
                        <span>{{ __('Attività utenti') }}</span>
                    </a>
                    <a href="{{ route('admin.courses.modules.video-exercises.responses-export', [$course, $module, $videoExercise]) }}" class="btn btn-primary">
                        <x-lucide-download class="h-4 w-4" />
                        <span>{{ __('Esporta risposte') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <dialog id="add-material-modal" class="modal" data-video-exercise-material-modal>
        <div class="modal-box max-w-2xl">
            <h3 class="mb-4 text-lg font-bold">{{ __('Nuovo asset didattico') }}</h3>
            <form method="POST" action="{{ route('admin.courses.modules.video-exercises.materials.store', [$course, $module, $videoExercise]) }}" enctype="multipart/form-data" class="grid gap-4">
                @csrf
                <div class="grid gap-4" data-material-step="1">
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('Titolo') }}</legend>
                        <input type="text" name="title" class="input input-bordered" required>
                    </fieldset>
                    <fieldset class="fieldset">
                        <legend class="fieldset-legend">{{ __('Tipo') }}</legend>
                        <select name="type" class="select select-bordered" required data-material-type>
                            <option value="file">{{ __('File PDF/Word') }}</option>
                            <option value="video">{{ __('Video YouTube') }}</option>
                            <option value="text">{{ __('Testo libero') }}</option>
                        </select>
                    </fieldset>
                </div>

                <div class="hidden grid gap-4" data-material-step="2">
                <fieldset class="fieldset" data-material-field="file">
                    <legend class="fieldset-legend">{{ __('File') }}</legend>
                    <input type="file" name="file" accept=".pdf,.doc,.docx" class="file-input file-input-bordered">
                </fieldset>
                <fieldset class="fieldset hidden" data-material-field="video">
                    <legend class="fieldset-legend">{{ __('URL YouTube') }}</legend>
                    <input type="url" name="youtube_url" class="input input-bordered" placeholder="https://www.youtube.com/watch?v=...">
                </fieldset>
                <fieldset class="fieldset hidden" data-material-field="text">
                    <legend class="fieldset-legend">{{ __('Testo libero') }}</legend>
                    <textarea id="material_content_html" name="content_html" class="hidden"></textarea>
                    <div class="rounded border border-base-300 bg-base-100">
                        <div class="flex flex-wrap gap-1 border-b border-base-300 p-2" data-module-tiptap-toolbar="material_content_html">
                            <button type="button" class="btn btn-xs btn-ghost" data-command="bold">
                                <x-lucide-bold class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="btn btn-xs btn-ghost" data-command="italic">
                                <x-lucide-italic class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="btn btn-xs btn-ghost" data-command="heading" data-level="2">H2</button>
                            <button type="button" class="btn btn-xs btn-ghost" data-command="paragraph">P</button>
                            <button type="button" class="btn btn-xs btn-ghost" data-command="bulletList">
                                <x-lucide-list class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="btn btn-xs btn-ghost" data-command="orderedList">
                                <x-lucide-list-ordered class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="btn btn-xs btn-ghost" data-command="undo">
                                <x-lucide-undo-2 class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="btn btn-xs btn-ghost" data-command="redo">
                                <x-lucide-redo-2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <div data-module-tiptap-editor data-target="material_content_html" class="min-h-32 p-3"></div>
                    </div>
                </fieldset>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-material-modal').close()">{{ __('Annulla') }}</button>
                    <button type="button" class="btn btn-outline hidden" data-material-back>{{ __('Indietro') }}</button>
                    <button type="button" class="btn btn-primary" data-material-next>{{ __('Avanti') }}</button>
                    <button type="submit" class="btn btn-primary hidden" data-material-submit>{{ __('Salva asset') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Chiudi') }}</button>
        </form>
    </dialog>

    <dialog id="add-question-modal" class="modal">
        <div class="modal-box max-w-xl">
            <h3 class="mb-4 text-lg font-bold">{{ __('Nuova domanda') }}</h3>
            <form method="POST" action="{{ route('admin.courses.modules.video-exercises.questions.store', [$course, $module, $videoExercise]) }}" class="grid gap-4">
                @csrf
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Domanda') }}</legend>
                    <textarea name="text" class="textarea textarea-bordered min-h-24" required></textarea>
                </fieldset>
                <fieldset class="fieldset">
                    <legend class="fieldset-legend">{{ __('Minimo caratteri') }}</legend>
                    <input type="number" name="minimum_characters" min="1" value="1" class="input input-bordered" required>
                </fieldset>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('add-question-modal').close()">{{ __('Annulla') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Salva domanda') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Chiudi') }}</button>
        </form>
    </dialog>

    @vite('resources/js/pages/admin-module-edit.js')
</x-layouts.admin>
