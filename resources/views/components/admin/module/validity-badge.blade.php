@props(['data' => []])

@php
    extract($data);
@endphp

<div id="module-validity-badge" class="flex items-center gap-3" data-validity-details data-validity-modal-target="#module-validity-details-modal">
    <span data-valid-badge class="badge badge-sm badge-success h-fit" style="display: {{ $isValid ? 'inline-flex' : 'none' }};">{{ __('Valido') }}</span>
    <button type="button" data-invalid-badge data-open-validity-details-modal class="badge badge-sm badge-error whitespace-nowrap cursor-pointer h-fit" style="display: {{ $isValid ? 'none' : 'inline-flex' }};">{{ __('Non valido') }}</button>
</div>

@push('after-form')
    <dialog id="module-validity-details-modal" class="modal" data-validity-details-modal>
        <div class="modal-box max-w-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold">{{ __('Dettagli validità modulo') }}</h3>
                    <p class="text-sm text-base-content/70">
                        {{ __('Completa i dati richiesti per rendere il modulo valido.') }}
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
                <ul class="space-y-2 text-sm text-base-content/80" data-validation-errors-list>
                    @foreach ($validationErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Chiudi') }}</button>
        </form>
    </dialog>
@endpush
