<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Pacchetti SCORM')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna al modulo') }}</span>
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Carica un nuovo pacchetto') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('L\'archivio ZIP viene validato, estratto e parsato prima di diventare disponibile al player.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.courses.modules.scorm.store', [$course, $module]) }}" enctype="multipart/form-data" class="grid gap-6">
                    @csrf

                    <div class="form-control flex flex-col gap-2">
                        <label for="package" class="label p-0">
                            <span class="label-text font-medium">{{ __('Archivio ZIP') }}</span>
                        </label>
                        <input id="package" name="package" type="file" accept=".zip,application/zip" class="file-input file-input-bordered w-full @error('package') file-input-error @enderror" required>
                        @error('package')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="form-control flex flex-col gap-2">
                            <label for="title" class="label p-0">
                                <span class="label-text font-medium">{{ __('Titolo personalizzato') }}</span>
                            </label>
                            <input id="title" name="title" type="text" value="{{ old('title') }}" class="input input-bordered w-full @error('title') input-error @enderror">
                            @error('title')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-control flex flex-col gap-2 md:col-span-2">
                            <label for="description" class="label p-0">
                                <span class="label-text font-medium">{{ __('Descrizione personalizzata') }}</span>
                            </label>
                            <textarea id="description" name="description" class="textarea textarea-bordered min-h-24 w-full @error('description') textarea-error @enderror">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <x-lucide-upload class="h-4 w-4" />
                            <span>{{ __('Carica pacchetto') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Pacchetti presenti') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Qui trovi i pacchetti caricati per questo modulo. Per ciascun pacchetto mostriamo versione SCORM rilevata, stato di elaborazione, identificativo del manifest e file iniziale che verrà aperto nel player.') }}
                    </p>
                </div>

                @if ($packages->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70">
                        {{ __('Nessun pacchetto SCORM caricato per questo modulo.') }}
                    </div>
                @else
                    <div class="grid gap-4">
                        @foreach ($packages as $package)
                            @php
                                $statusLabel = match ($package->status) {
                                    'pending' => __('In attesa'),
                                    'processing' => __('In elaborazione'),
                                    'ready' => __('Pronto'),
                                    'error' => __('Errore'),
                                    default => $package->status,
                                };
                            @endphp
                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div class="min-w-0 flex-1 space-y-4">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0 space-y-1">
                                                <p class="text-base font-semibold">{{ $package->title }}</p>
                                                <p class="text-sm text-base-content/70">
                                                    {{ $package->description ?: __('Nessuna descrizione disponibile.') }}
                                                </p>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="badge badge-ghost">
                                                    {{ __('SCORM :version', ['version' => strtoupper($package->version ?? 'N/D')]) }}
                                                </span>
                                                <span class="badge @class([
                                                'badge-success' => $package->status === 'ready',
                                                'badge-warning' => $package->status === 'processing',
                                                'badge-error' => $package->status === 'error',
                                                'badge-ghost' => ! in_array($package->status, ['ready', 'processing', 'error'], true),
                                            ])">{{ $statusLabel }}</span>
                                                @if ($package->isReady())
                                                    <span class="badge badge-outline">{{ __('Pronto per l\'apertura') }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="grid gap-3 md:grid-cols-2">
                                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">
                                                    {{ __('Identificativo del pacchetto') }}
                                                </p>
                                                <p class="mt-2 break-all text-sm text-base-content/80">
                                                    {{ $package->identifier ?: '—' }}
                                                </p>
                                            </div>

                                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">
                                                    {{ __('File iniziale') }}
                                                </p>
                                                <p class="mt-2 break-all text-sm text-base-content/80">
                                                    {{ $package->entry_point ?: '—' }}
                                                </p>
                                            </div>
                                        </div>

                                        @if ($package->error_message)
                                            <div class="rounded-box border border-error/30 bg-error/5 p-3">
                                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-error">
                                                    {{ __('Dettaglio errore') }}
                                                </p>
                                                <p class="mt-2 text-sm text-error">{{ $package->error_message }}</p>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex shrink-0 items-start gap-2">
                                        <form method="POST" action="{{ route('admin.courses.modules.scorm.destroy', [$course, $module, $package]) }}">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="btn btn-accent btn-outline">
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                                <span>{{ __('Elimina') }}</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
