@props(['data' => []])

@php
    extract($data);
@endphp

<div id="quiz-validity-badge" class="flex items-center gap-3" data-validity-details data-validity-modal-target="#quiz-validity-details-modal">
    <span data-valid-badge class="badge badge-sm badge-success h-fit" style="display: {{ $module->isValidQuiz() ? 'inline-flex' : 'none' }};">{{ __('Valido') }}</span>
    <button type="button" data-invalid-badge data-open-validity-details-modal class="badge badge-sm badge-error whitespace-nowrap cursor-pointer h-fit" style="display: {{ $module->isValidQuiz() ? 'none' : 'inline-flex' }};">{{ __('Non valido') }}</button>
</div>

@push('after-form')
    <dialog id="quiz-validity-details-modal" class="modal" data-validity-details-modal>
        <div class="modal-box max-w-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold">{{ __('Dettagli validità quiz') }}</h3>
                    <p class="text-sm text-base-content/70">
                        {{ __('Controlla i requisiti necessari per rendere il quiz valido.') }}
                    </p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-validity-details-modal>
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>

            <div class="mt-6 rounded-box border border-error/30 bg-error/5 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <span class="badge badge-error badge-soft h-fit">{{ __('Non valido') }}</span>
                    <span class="font-medium">{{ __('Errori di validità') }}</span>
                </div>
                <p data-invalid-reason class="text-sm text-base-content/80">
                    {{ __('Deve avere almeno una domanda valida, il punteggio di superamento non può essere più alto del punteggio massimo e i tentativi devono essere maggiori di zero.') }}
                </p>
            </div>
        </div>

        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Chiudi') }}</button>
        </form>
    </dialog>
@endpush
