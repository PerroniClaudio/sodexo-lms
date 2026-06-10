<dialog class="modal" data-survey-distribution-modal>
    <div class="modal-box w-11/12 max-w-4xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Distribuzione risposte') }}</p>
                <h3 class="mt-1 text-lg font-semibold" data-survey-distribution-title></h3>
            </div>
            <form method="dialog">
                <button type="submit" class="btn btn-ghost btn-sm btn-circle" aria-label="{{ __('Chiudi') }}">X</button>
            </form>
        </div>

        <div class="mt-6 h-80">
            <canvas data-survey-distribution-canvas></canvas>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop">
        <button type="submit">{{ __('Chiudi') }}</button>
    </form>
</dialog>
