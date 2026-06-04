<div id="module-validity-badge" class="flex items-center gap-3" data-validity-details>
    @if ($isValid)
        <span class="badge badge-sm badge-success">{{ __('Valido') }}</span>
    @else
        <button type="button" class="badge badge-sm badge-error whitespace-nowrap cursor-pointer" data-open-validity-details-modal>
            {{ __('Non valido') }}
        </button>
    @endif
</div>

@if (! $isValid && ! empty($validationErrors))
    <dialog class="modal" data-validity-details-modal>
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
                    <span class="badge badge-error badge-soft">{{ __('Non valido') }}</span>
                    <span class="font-medium">{{ __('Errori di validità') }}</span>
                </div>
                <ul class="space-y-2 text-sm text-base-content/80">
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
@endif
