<x-layouts.admin>
    <div
        class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-satisfaction-survey-page
        data-index-url="{{ $indexUrl }}"
        data-store-url="{{ $storeUrl }}"
        data-reorder-url="{{ $reorderUrl }}"
        data-course-type-labels='@json($courseTypeLabels)'
    >
        <template data-move-icon-template>
            <x-lucide-move class="h-3.5 w-3.5" />
        </template>

        <template data-answer-field-template>
            <label class="form-control gap-2">
                <span class="label-text text-sm" data-answer-field-label></span>
                <input type="text" name="answers[]" value="" class="input input-bordered w-full">
            </label>
        </template>

        <template data-question-item-template>
            <article class="rounded-box border border-base-300 bg-base-100 p-4 shadow-sm" data-question-item>
                <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge badge-outline gap-1 cursor-move h-fit" data-question-order-badge>
                                <span data-question-move-icon></span>
                                <span data-question-order></span>
                            </span>
                            <span class="badge h-fit" data-question-input-type-badge></span>
                            <span class="badge badge-warning badge-outline hidden h-fit" data-question-textarea-badge>{{ __('Sempre in fondo') }}</span>
                        </div>
                        <p class="font-semibold" data-question-text></p>
                        <ol class="ml-5 hidden list-decimal space-y-1 text-sm text-base-content/70" data-question-answers-list></ol>
                        <p class="hidden text-sm text-base-content/70" data-question-textarea-note>{{ __('Risposta libera tramite textarea.') }}</p>
                        <div class="flex flex-wrap gap-2 text-xs" data-question-excluded-types></div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" class="btn btn-primary btn-sm" data-action="edit">{{ __('Modifica') }}</button>
                        <button type="button" class="btn btn-error btn-outline btn-sm" data-action="delete">{{ __('Elimina') }}</button>
                    </div>
                </div>
            </article>
        </template>

        <template data-question-answer-item-template>
            <li></li>
        </template>

        <template data-question-excluded-type-badge-template>
            <span class="badge badge-ghost h-fit"></span>
        </template>

        <template data-question-no-excluded-types-template>
            <span class="text-base-content/60">{{ __('Nessuna tipologia esclusa.') }}</span>
        </template>

        <x-page-header :title="__('Questionario di gradimento')">
            <x-slot:actions>
                <button type="button" class="btn btn-primary" data-open-create-modal>
                    {{ __('Nuova domanda') }}
                </button>
            </x-slot:actions>
        </x-page-header>

        <div class="alert alert-info">
            <span>{{ __('Le domande a risposta libera vengono sempre mostrate in fondo al questionario. Puoi riordinarle tra loro, ma non supereranno mai le domande a risposta multipla.') }}</span>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="card-title">{{ __('Domande configurate') }}</h2>
                        <p class="text-sm text-base-content/70" data-questions-summary>{{ __('Caricamento in corso...') }}</p>
                    </div>
                    <span class="loading loading-spinner loading-sm hidden" data-questions-loading></span>
                </div>

                <div class="hidden rounded-box border border-dashed border-base-300 px-6 py-10 text-center text-sm text-base-content/60" data-empty-state>
                    {{ __('Nessuna domanda configurata.') }}
                </div>

                <div class="flex flex-col gap-3" data-questions-list></div>
            </div>
        </div>

        <dialog class="modal" data-question-modal>
            <div class="modal-box max-w-3xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold" data-question-modal-title>{{ __('Nuova domanda') }}</h3>
                        <p class="mt-1 text-sm text-base-content/70">{{ __('Le domande a risposta multipla devono avere esattamente 5 opzioni.') }}</p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-question-modal>✕</button>
                </div>

                <form class="mt-6 flex flex-col gap-5" data-question-form>
                    <input type="hidden" name="question_id" value="">

                    <div class="form-control gap-2">
                        <label class="label p-0" for="survey-question-text">
                            <span class="label-text font-medium">{{ __('Domanda') }}</span>
                        </label>
                        <textarea id="survey-question-text" name="text" class="textarea textarea-bordered min-h-28 w-full" required></textarea>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="form-control gap-2">
                            <label class="label p-0" for="survey-question-input-type">
                                <span class="label-text font-medium">{{ __('Tipo input') }}</span>
                            </label>
                            <select id="survey-question-input-type" name="input_type" class="select select-bordered w-full">
                                @foreach ($inputTypes as $inputType)
                                    <option value="{{ $inputType }}">{{ strtoupper($inputType) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-control gap-2">
                            <label class="label p-0">
                                <span class="label-text font-medium">{{ __('Tipologie che possono saltarla') }}</span>
                            </label>
                            <div class="grid gap-2 rounded-box border border-base-300 p-3 sm:grid-cols-2" data-course-type-checkboxes>
                                @foreach ($courseTypeLabels as $courseType => $label)
                                    <label class="flex items-center gap-3 text-sm">
                                        <input type="checkbox" class="checkbox checkbox-sm" name="excluded_course_types[]" value="{{ $courseType }}">
                                        <span>{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3" data-answers-panel>
                        <div class="flex items-center justify-between gap-4">
                            <p class="font-medium">{{ __('Risposte') }}</p>
                            <span class="text-xs text-base-content/60">{{ __('Esattamente 5 voci') }}</span>
                        </div>
                        <div class="grid gap-3" data-answers-fields></div>
                    </div>

                    <p class="hidden rounded-box border border-error/30 bg-error/10 px-4 py-3 text-sm text-error" data-question-form-error></p>

                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn btn-ghost" data-close-question-modal>{{ __('Annulla') }}</button>
                        <button type="submit" class="btn btn-primary" data-question-submit>{{ __('Salva') }}</button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>{{ __('Close') }}</button>
            </form>
        </dialog>

        <dialog class="modal" data-delete-modal>
            <div class="modal-box max-w-lg">
                <h3 class="text-lg font-semibold">{{ __('Elimina domanda') }}</h3>
                <p class="mt-3 text-sm text-base-content/70" data-delete-description></p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="btn btn-ghost" data-close-delete-modal>{{ __('Annulla') }}</button>
                    <button type="button" class="btn btn-error" data-confirm-delete>{{ __('Elimina') }}</button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>{{ __('Close') }}</button>
            </form>
        </dialog>
    </div>

    @vite('resources/js/pages/admin-satisfaction-survey-edit.js')
</x-layouts.admin>
