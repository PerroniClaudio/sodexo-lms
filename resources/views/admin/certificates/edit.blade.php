<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Modifica attestato')"
            :description="__('Gestisci template, versioni e preview DOCX.')"
        >
            <x-slot:actions>
                <a href="{{ route('admin.certificates.preview', $certificate) }}" class="btn btn-primary btn-outline">
                    <x-lucide-download class="h-4 w-4" />
                    <span>{{ __('Download di prova') }}</span>
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-box border border-base-300 p-4">
                            <p class="text-sm text-base-content/70">{{ __('Stato') }}</p>
                            <p class="mt-1 font-semibold">
                                @if ($certificate->is_active)
                                    <span class="badge badge-success badge-outline">{{ __('Attivo') }}</span>
                                @else
                                    <span class="badge badge-outline">{{ __('Versione storica') }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="rounded-box border border-base-300 p-4">
                            <p class="text-sm text-base-content/70">{{ __('File corrente') }}</p>
                            <p class="mt-1 font-semibold">{{ $certificate->original_filename }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.certificates.update', $certificate) }}" enctype="multipart/form-data" class="flex flex-col gap-6">
                        @csrf
                        @method('PUT')

                        @include('admin.certificates.partials.form', [
                            'requireTemplateUpload' => false,
                        ])

                        <div class="flex justify-end gap-3">
                            <a href="{{ route('admin.certificates.index') }}" class="btn btn-ghost">
                                {{ __('Cancel') }}
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <span>{{ __('Salva modifiche') }}</span>
                                <x-lucide-check class="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-3">
                        <h2 class="card-title text-base">{{ __('Dettagli versione') }}</h2>
                        <p><span class="font-medium">{{ __('Tipo:') }}</span> {{ $typeLabels[$certificate->type] ?? $certificate->type }}</p>
                        <p><span class="font-medium">{{ __('Attivato:') }}</span> {{ $certificate->activated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                        <p><span class="font-medium">{{ __('Archiviato:') }}</span> {{ $certificate->archived_at?->format('d/m/Y H:i') ?? '-' }}</p>
                        <p><span class="font-medium">{{ __('Corsi associati:') }}</span> {{ filled($certificate->course_ids) ? count($certificate->course_ids) : 0 }}</p>
                    </div>
                </div>

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <h2 class="card-title text-base">{{ __('Versioni precedenti') }}</h2>

                        @forelse ($previousVersions as $previousVersion)
                            <div class="rounded-box border border-base-300 p-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold">{{ $previousVersion->name }}</p>
                                        <p class="text-sm text-base-content/70">{{ $previousVersion->original_filename }}</p>
                                        <p class="text-sm text-base-content/70">{{ $previousVersion->activated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                                    </div>

                                    @if (! $previousVersion->is_active)
                                        <form method="POST" action="{{ route('admin.certificates.restore-version', $previousVersion) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline">
                                                {{ __('Ripristina') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/70">{{ __('Nessuna versione precedente disponibile.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
