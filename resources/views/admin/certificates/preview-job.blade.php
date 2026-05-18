<x-layouts.admin>
    @php
        $status = $documentConversionJob->status;
        $isPending = $status === \App\Enums\DocumentConversionJobStatus::PENDING;
        $isProcessing = $status === \App\Enums\DocumentConversionJobStatus::PROCESSING;
        $isCompleted = $status === \App\Enums\DocumentConversionJobStatus::COMPLETED && $documentConversionJob->hasGeneratedFile();
        $isFailed = $status === \App\Enums\DocumentConversionJobStatus::FAILED;
        $currentStep = $isCompleted ? 3 : ($isProcessing ? 2 : 1);
        $title = $isCompleted
            ? __('Attestato pronto')
            : ($isFailed ? __('Si è verificato un problema') : __('Stiamo preparando il tuo attestato'));
        $message = $isCompleted
            ? __('Il PDF di prova è pronto. Puoi scaricarlo subito.')
            : ($isFailed
                ? __('La conversione non è andata a buon fine. Riprova tra poco o contatta il supporto.')
                : __('L’operazione richiede solo qualche secondo. La pagina si aggiorna automaticamente.'));
        $steps = [
            ['label' => __('Richiesta inviata'), 'done' => true, 'active' => $currentStep === 1],
            ['label' => __('Elaborazione'), 'done' => $currentStep >= 2, 'active' => $currentStep === 2],
            ['label' => __('PDF pronto'), 'done' => $currentStep >= 3, 'active' => $currentStep === 3],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
            <div class="bg-base-100 px-6 py-8 sm:px-10 sm:py-10">
                <div class="mx-auto flex max-w-2xl flex-col items-center gap-8 text-center">
                    <div class="space-y-3">
                        <h1 class="text-3xl font-semibold text-base-content sm:text-4xl">{{ $title }}</h1>
                        <p class="mx-auto max-w-xl text-base text-base-content/70 sm:text-lg">{{ $message }}</p>
                    </div>

                    <div class="w-full max-w-xl space-y-4">
                        <div class="grid grid-cols-3 gap-3 text-sm font-medium text-base-content/75">
                            @foreach ($steps as $step)
                                <div class="flex flex-col items-center gap-2">
                                    <div @class([
                                        'flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold transition',
                                        'border-success bg-success text-success-content' => $step['done'],
                                        'border-secondary bg-secondary text-secondary-content ring-4 ring-secondary/15' => $step['active'] && ! $step['done'],
                                        'border-base-300 bg-base-100 text-base-content/50' => ! $step['done'] && ! $step['active'],
                                    ])>
                                        {{ $loop->iteration }}
                                    </div>
                                    <span>{{ $step['label'] }}</span>
                                </div>
                            @endforeach
                        </div>

                        <div class="h-4 overflow-hidden rounded-full bg-base-200">
                            <div @class([
                                'h-full rounded-full transition-all duration-500',
                                'w-1/3 bg-primary' => $currentStep === 1 && ! $isFailed,
                                'w-2/3 bg-secondary' => $currentStep === 2 && ! $isFailed,
                                'w-full bg-success' => $isCompleted,
                                'w-full bg-error' => $isFailed,
                            ])></div>
                        </div>

                        @if ($isFailed)
                            <p class="text-sm text-base-content/60">{{ __('Dettaglio: :message', ['message' => $documentConversionJob->error_message ?: __('Errore temporaneo di conversione.')]) }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap justify-center gap-3">
                        @if ($shouldAutoRefresh)
                            <a href="{{ route('admin.certificates.preview-job', [$certificate, $documentConversionJob]) }}" class="btn btn-outline">
                                {{ __('Aggiorna ora') }}
                            </a>
                        @endif

                        @if ($isCompleted)
                            <a href="{{ route('admin.certificates.preview-job-download', [$certificate, $documentConversionJob]) }}" class="btn btn-primary">
                                <x-lucide-download class="h-4 w-4" />
                                <span>{{ __('Scarica PDF di prova') }}</span>
                            </a>
                        @else
                            <a href="{{ route('admin.certificates.preview', $certificate) }}" class="btn btn-ghost">
                                {{ __('Torna alla preview') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($shouldAutoRefresh)
        <script>
            window.setTimeout(function () {
                window.location.reload();
            }, 5000);
        </script>
    @endif
</x-layouts.admin>
