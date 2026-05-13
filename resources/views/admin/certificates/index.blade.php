<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Attestati')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.certificates.create')"
        />

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach ($typeLabels as $type => $label)
                @php($activeCertificate = $activeCertificates[$type] ?? null)
                @php($history = $certificateHistory->get($type, collect()))

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <div class="flex items-center justify-between gap-4">
                            <h2 class="card-title">{{ $label }}</h2>
                            @if ($activeCertificate)
                                <span class="badge badge-success badge-outline">{{ __('Attivo') }}</span>
                            @else
                                <span class="badge badge-outline">{{ __('Non configurato') }}</span>
                            @endif
                        </div>

                        @if ($activeCertificate)
                            <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                <p class="font-semibold">{{ $activeCertificate->name }}</p>
                                <p class="text-sm text-base-content/70">{{ $activeCertificate->original_filename }}</p>
                                <p class="text-sm text-base-content/70">
                                    {{ __('Attivato il :date', ['date' => $activeCertificate->activated_at?->format('d/m/Y H:i') ?? '-']) }}
                                </p>
                                <div class="mt-4 flex gap-3">
                                    <a href="{{ route('admin.certificates.edit', $activeCertificate) }}" class="btn btn-sm btn-primary">
                                        {{ __('Gestisci') }}
                                    </a>
                                    <a href="{{ route('admin.certificates.preview', $activeCertificate) }}" class="btn btn-sm btn-outline">
                                        {{ __('Preview') }}
                                    </a>
                                </div>
                            </div>
                        @else
                            <p class="text-sm text-base-content/70">{{ __('Nessun template attivo disponibile per questo tipo.') }}</p>
                        @endif

                        <div class="divider my-0">{{ __('Cronologia') }}</div>

                        <div class="flex flex-col gap-3">
                            @forelse ($history as $certificate)
                                <div class="rounded-box border border-base-300 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="font-semibold">{{ $certificate->name }}</p>
                                            <p class="text-sm text-base-content/70">{{ $certificate->original_filename }}</p>
                                            <p class="text-sm text-base-content/70">{{ $certificate->activated_at?->format('d/m/Y H:i') ?? '-' }}</p>
                                        </div>

                                        <a href="{{ route('admin.certificates.edit', $certificate) }}" class="btn btn-sm btn-ghost">
                                            {{ __('Apri') }}
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/70">{{ __('Nessuna versione disponibile.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.admin>
