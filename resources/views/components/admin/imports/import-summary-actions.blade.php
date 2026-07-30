@props(['importazione'])

<div class="flex gap-2 whitespace-nowrap">
    <a
        href="{{ route('admin.imports.download', $importazione) }}"
        class="btn btn-square btn-sm btn-outline tooltip tooltip-left"
        data-tip="{{ __('Scarica file :name', ['name' => $importazione->fileName()]) }}"
        aria-label="{{ __('Scarica file utilizzato') }}"
    >
        <x-lucide-download class="h-4 w-4" />
    </a>
    <button type="button" class="btn btn-square btn-sm btn-outline tooltip tooltip-left" data-tip="{{ __('Informazioni e log importazione') }}" onclick="document.getElementById('import-summary-{{ $importazione->id }}').showModal()" aria-label="{{ __('Informazioni e log importazione') }}">
        <x-lucide-file-search class="h-4 w-4" />
    </button>
</div>

<dialog id="import-summary-{{ $importazione->id }}" class="modal">
    <div class="modal-box max-w-2xl">
        <h3 class="text-lg font-semibold">{{ __('Riepilogo importazione #:id', ['id' => $importazione->id]) }}</h3>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-base-content/60">{{ __('Tipo') }}</dt><dd class="font-medium">{{ $importazione->typeLabel() }}</dd></div>
            <div><dt class="text-base-content/60">{{ __('Stato') }}</dt><dd class="font-medium">{{ $importazione->statusLabel() }}</dd></div>
            <div class="sm:col-span-2">
                <dt class="text-base-content/60">{{ __('File') }}</dt>
                <dd class="break-all font-medium">{{ $importazione->fileName() }}</dd>
                <dd class="mt-1 break-all text-xs text-base-content/60">{{ $importazione->file_path }}</dd>
            </div>
        </dl>

        @if ($importazione->summaryItems() !== [])
            <div class="mt-5">
                <h4 class="font-medium">{{ __('Esito') }}</h4>
                <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ($importazione->summaryItems() as $label => $value)
                        <div class="flex justify-between rounded-box bg-base-200 px-3 py-2"><dt>{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>
        @endif

        @if ($importazione->error_message)
            <div class="mt-5">
                <h4 class="font-medium text-error">{{ __('Log errori') }}</h4>
                <pre class="mt-2 max-h-64 overflow-auto whitespace-pre-wrap rounded-box border border-error/30 bg-error/10 p-3 text-sm">{{ $importazione->error_message }}</pre>
            </div>
        @endif

        <div class="modal-action">
            <form method="dialog"><button class="btn">{{ __('Chiudi') }}</button></form>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>{{ __('Chiudi') }}</button></form>
</dialog>
