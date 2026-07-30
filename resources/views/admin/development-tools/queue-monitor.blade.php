<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Monitor coda')">
            {{ __('Disponibile solo in sviluppo. Aggiorna la pagina per vedere gli ultimi dati.') }}
        </x-page-header>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="stat-title">{{ __('Worker attivi') }}</div>
                <div class="stat-value text-primary">{{ $activeWorkers->count() }}</div>
                <div class="stat-desc">{{ __('Heartbeat negli ultimi 10 secondi') }}</div>
            </div>
            <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="stat-title">{{ __('Job in coda') }}</div>
                <div class="stat-value text-warning">{{ $pendingJobs->count() }}</div>
                <div class="stat-desc">{{ __('Ultimi 50 job pendenti o riservati') }}</div>
            </div>
            <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="stat-title">{{ __('Job falliti') }}</div>
                <div class="stat-value text-error">{{ $failedJobs->count() }}</div>
                <div class="stat-desc">{{ __('Ultimi 50 errori registrati') }}</div>
            </div>
        </div>

        <section class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <h2 class="card-title">{{ __('Worker attivi') }}</h2>
                @forelse ($activeWorkers as $worker)
                    <div class="rounded-box bg-base-200 p-3 text-sm">
                        <span class="font-medium">{{ $worker->worker_id }}</span>
                        <span class="text-base-content/60">{{ $worker->connection }} / {{ $worker->queue }} · {{ $worker->last_seen_at }}</span>
                    </div>
                @empty
                    <p class="text-sm text-base-content/60">{{ __('Nessun worker rilevato. Avvia composer dev:windows e aggiorna la pagina.') }}</p>
                @endforelse
            </div>
        </section>

        <x-admin.development-tools.queue-table :title="__('Job in coda')" :jobs="$pendingJobs" state="pending" />
        <x-admin.development-tools.queue-table :title="__('Job riusciti')" :jobs="$completedJobs" state="completed" />
        <x-admin.development-tools.queue-table :title="__('Job falliti')" :jobs="$failedJobs" state="failed" />
    </div>
</x-layouts.admin>
