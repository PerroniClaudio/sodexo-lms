<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Docenti e tutor corsi')">
            <x-slot:actions>
                <a href="{{ route('admin.imports.course-staff.template') }}" class="btn btn-outline">
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
            <div class="alert alert-success"><span>{{ session('status') }}</span></div>
        @endif

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <h2 class="card-title">{{ __('Carica file Excel') }}</h2>

                <form method="POST" action="{{ route('admin.imports.course-staff.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <div>
                        <label for="file" class="label p-0"><span class="label-text font-medium">{{ __('File Excel') }}</span></label>
                        <input id="file" type="file" name="file" accept=".xlsx,.xls" class="file-input file-input-bordered mt-2 w-full @error('file') file-input-error @enderror">
                        <p class="mt-2 text-xs text-base-content/60">{{ __('Formati supportati: .xlsx e .xls') }}</p>
                        @error('file')<p class="mt-2 text-sm text-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
                        <p class="font-medium">{{ __('Regole applicate') }}</p>
                        <ul class="mt-3 list-disc space-y-2 pl-5">
                            <li>{{ __('Email, codice corso e ruolo sono obbligatori.') }}</li>
                            <li>{{ __('Il ruolo deve essere docente oppure tutor e l’utente deve già possedere il ruolo corrispondente.') }}</li>
                            <li>{{ __('Le assegnazioni già attive non vengono duplicate; quelle eliminate vengono ripristinate.') }}</li>
                            <li>{{ __('Dopo l’importazione, docenti e tutor potranno essere assegnati ai singoli moduli del corso.') }}</li>
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

        <div data-course-staff-import-status-card data-status-url="{{ route('admin.imports.course-staff.status-card') }}">
            <x-admin.imports.course-staff-status-card :recent-imports="$recentImports" />
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const statusCard = document.querySelector('[data-course-staff-import-status-card]');

                if (! statusCard) {
                    return;
                }

                window.setInterval(async function () {
                    if (statusCard.querySelector('dialog[open]')) {
                        return;
                    }

                    try {
                        const response = await fetch(statusCard.dataset.statusUrl, {
                            headers: {'X-Requested-With': 'XMLHttpRequest'},
                        });

                        if (response.ok && ! statusCard.querySelector('dialog[open]')) {
                            statusCard.innerHTML = await response.text();
                        }
                    } catch (error) {
                        console.error(error);
                    }
                }, 3000);
            });
        </script>
    @endpush
</x-layouts.admin>
