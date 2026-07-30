<x-layouts.admin>
    <div class="p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Altre configurazioni')"
            :subtitle="__('Impostazioni globali del portale.')"
        />

        <section class="mt-6 rounded-box border border-base-300 bg-base-100 p-6 shadow-sm">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Favicon') }}</h2>
                    <p class="mt-1 text-sm text-base-content/70">
                        {{ __('Carica un file .ico valido (massimo 100 KB). La nuova favicon sostituirà quella attuale e verrà sempre salvata come favicon.ico.') }}
                    </p>
                </div>

                <div class="flex size-24 shrink-0 items-center justify-center rounded-box border border-base-300 bg-base-200 p-4">
                    <img
                        id="favicon-preview"
                        src="{{ $faviconUrl }}"
                        alt="{{ __('Favicon attuale') }}"
                        class="size-16 object-contain {{ $faviconUrl ? '' : 'hidden' }}"
                    >
                    <span id="favicon-placeholder" class="text-center text-xs text-base-content/60 {{ $faviconUrl ? 'hidden' : '' }}">
                        {{ __('Nessuna favicon') }}
                    </span>
                </div>
            </div>

            <form id="favicon-form" method="POST" action="{{ route('admin.other-configurations.favicon.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-4 border-t border-base-300 pt-6 sm:grid-cols-[1fr_auto] sm:items-end">
                @csrf

                <label class="form-control w-full">
                    <span class="label">
                        <span class="label-text font-semibold">{{ __('Nuova favicon') }}</span>
                    </span>
                    <input id="favicon-input" type="file" name="favicon" accept=".ico,image/x-icon,image/vnd.microsoft.icon" class="file-input file-input-bordered w-full" required>
                    <span id="favicon-error" class="label-text-alt mt-2 hidden text-error" role="alert"></span>
                </label>

                <button id="favicon-submit" type="submit" class="btn btn-primary">
                    {{ __('Carica favicon') }}
                </button>
            </form>

            <p id="favicon-status" class="mt-4 hidden text-sm" role="status" aria-live="polite"></p>
        </section>
    </div>

    @push('scripts')
        <script>
            document.getElementById('favicon-form').addEventListener('submit', async function (event) {
                event.preventDefault();

                var form = event.currentTarget;
                var submit = document.getElementById('favicon-submit');
                var status = document.getElementById('favicon-status');
                var error = document.getElementById('favicon-error');

                submit.disabled = true;
                error.classList.add('hidden');
                status.classList.add('hidden');

                try {
                    var response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    var data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.errors?.favicon?.[0] || data.message || '{{ __('Caricamento non riuscito.') }}');
                    }

                    document.getElementById('favicon-preview').src = data.favicon_url;
                    document.getElementById('favicon-preview').classList.remove('hidden');
                    document.getElementById('favicon-placeholder').classList.add('hidden');
                    form.reset();
                    status.textContent = data.message;
                    status.className = 'mt-4 text-sm text-success';
                } catch (exception) {
                    error.textContent = exception.message;
                    error.classList.remove('hidden');
                } finally {
                    submit.disabled = false;
                }
            });
        </script>
    @endpush
</x-layouts.admin>
