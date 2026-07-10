@props(['course', 'courseValidator', 'jobBasedRequirements', 'updateUrl'])

<div class="flex flex-col gap-6" data-course-job-based-requirements>
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Requisiti ruolo / mansione soddisfatti') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Quando l’utente completa il corso, l’attestato interno soddisfa i requisiti selezionati.') }}
                    </p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" data-open-job-based-requirements-modal>
                    <span>{{ __('Aggiungi') }}</span>
                    <x-lucide-plus class="h-4 w-4" />
                </button>
            </div>

            <form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6">
                @csrf
                @method('PUT')

                <div class="grid gap-3" data-job-based-requirements-list></div>
                <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70" data-job-based-requirements-empty>
                    {{ __('Nessun requisito ruolo/mansione associato al corso.') }}
                </div>
                <div data-job-based-requirements-inputs></div>

                <script type="application/json" data-job-based-requirements-all>{!! json_encode($jobBasedRequirements->map(fn ($requirement) => ['id' => $requirement->getKey(), 'name' => $requirement->name, 'description' => $requirement->description])->values(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
                <script type="application/json" data-job-based-requirements-selected>{!! json_encode($course->jobBasedRequirements->map(fn ($requirement) => ['id' => $requirement->getKey(), 'name' => $requirement->name, 'description' => $requirement->description])->values(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

                @error('job_based_requirement_ids')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <span>{{ __('Salva requisiti') }}</span>
                        <x-lucide-save class="h-4 w-4" />
                    </button>
                </div>
            </form>

            <dialog class="modal" data-job-based-requirements-modal>
                <div class="modal-box max-w-4xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold">{{ __('Aggiungi requisito ruolo / mansione') }}</h3>
                            <p class="text-sm text-base-content/70">{{ __('Scegli il requisito soddisfatto dal completamento del corso.') }}</p>
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-job-based-requirements-modal>
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-6 overflow-x-auto rounded-box border border-base-300">
                        <table class="table w-full">
                            <thead><tr><th>{{ __('Nome') }}</th><th>{{ __('Descrizione') }}</th><th class="text-right">{{ __('Azioni') }}</th></tr></thead>
                            <tbody data-job-based-requirements-modal-body></tbody>
                        </table>
                    </div>
                    <div class="mt-4 hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70" data-job-based-requirements-modal-empty>
                        {{ __('Tutti i requisiti disponibili sono già associati al corso.') }}
                    </div>
                    <div class="modal-action"><button type="button" class="btn btn-ghost" data-close-job-based-requirements-modal>{{ __('Chiudi') }}</button></div>
                </div>
                <button type="button" class="modal-backdrop" data-close-job-based-requirements-modal>{{ __('Chiudi') }}</button>
            </dialog>
        </div>
    </div>
</div>
