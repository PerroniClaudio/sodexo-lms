<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Import utenti')">
            <x-slot:actions>
                <a href="{{ route('admin.imports.users.template') }}" class="btn btn-outline">
                    <x-lucide-download class="h-4 w-4" />
                    <span>{{ __('Scarica template') }}</span>
                </a>
                @if ((session('active_role') ?? auth()->user()?->getRoleNames()->first()) === 'superadmin')
                    <a href="{{ route('admin.importazioni-monitor.index') }}" class="btn btn-outline">
                        <x-lucide-list-checks class="h-4 w-4" />
                        <span>{{ __('Monitor importazioni') }}</span>
                    </a>
                @endif
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <div class="alert alert-success">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Carica file Excel') }}</h2>
                   
                </div>

                <form method="POST" action="{{ route('admin.imports.users.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <label for="file" class="label p-0">
                            <span class="label-text font-medium">{{ __('File Excel') }}</span>
                        </label>
                        <input
                            id="file"
                            type="file"
                            name="file"
                            accept=".xlsx,.xls"
                            class="file-input file-input-bordered mt-2 w-full @error('file') file-input-error @enderror"
                        >
                        <p class="mt-2 text-xs text-base-content/60">
                            {{ __('Formati supportati: .xlsx e .xls') }}
                        </p>
                        @error('file')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
                        <p class="font-medium">{{ __('Regole applicate') }}</p>
                        <ul class="mt-3 list-disc space-y-2 pl-5">
                            <li>{{ __('Obbligatori sempre: tipo di account, nome, cognome, codice fiscale.') }}</li>
                            <li>{{ __('Obbligatori se tra i ruoli c’è User: settore, ruolo, mansione, unità lavorativa, straniero, data assunzione, livello lingua.') }}</li>
                            <li>{{ __('Tipo di account multiplo separato da punto e virgola (;). Valori attesi: User, Docente, Tutor, Admin.') }}</li>
                            <li>{{ __('Categoria lavoro, settore, livello e ruolo devono esistere.') }}</li>
                            <li>{{ __(' Mansione e unità lavorativa sono risolte per codice.') }}</li>
                        </ul>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <x-lucide-file-up class="h-4 w-4" />
                            <span>{{ __('Avvia importazione') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            data-user-import-status-card
            data-status-url="{{ route('admin.imports.users.status-card') }}"
        >
            @include('admin.imports.partials.users-status-card', ['recentImports' => $recentImports])
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const statusCard = document.querySelector('[data-user-import-status-card]');

                if (! statusCard) {
                    return;
                }

                const statusUrl = statusCard.dataset.statusUrl;

                const refreshStatusCard = async function () {
                    try {
                        const response = await fetch(statusUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (! response.ok) {
                            return;
                        }

                        statusCard.innerHTML = await response.text();
                    } catch (error) {
                        console.error(error);
                    }
                };

                window.setInterval(refreshStatusCard, 3000);
            });
        </script>
    @endpush
</x-layouts.admin>
