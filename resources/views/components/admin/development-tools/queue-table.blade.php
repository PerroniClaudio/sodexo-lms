@props(['title', 'jobs', 'state'])

<section class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4 p-0">
        <h2 class="card-title px-6 pt-6">{{ $title }}</h2>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Job') }}</th>
                        <th>{{ __('Coda') }}</th>
                        <th>{{ __('Tentativi') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('Data') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td class="font-medium">{{ $job->job_name }}</td>
                            <td>{{ $job->queue }}</td>
                            <td>{{ $job->attempts ?? '-' }}</td>
                            <td>
                                <span @class([
                                    'badge badge-outline',
                                    'badge-warning' => $state === 'pending',
                                    'badge-success' => $state === 'completed',
                                    'badge-error' => $state === 'failed',
                                ])>
                                    {{ match ($state) {
                                        'pending' => $job->reserved_at ? __('In lavorazione') : __('In coda'),
                                        'completed' => __('Riuscito'),
                                        default => __('Fallito'),
                                    } }}
                                </span>
                            </td>
                            <td class="text-sm text-base-content/70">
                                {{ $state === 'pending'
                                    ? \Illuminate\Support\Carbon::createFromTimestamp($job->created_at)->format('d/m/Y H:i:s')
                                    : ($job->finished_at ?? $job->failed_at)?->format('d/m/Y H:i:s') }}
                            </td>
                        </tr>
                        @if ($state === 'failed')
                            <tr>
                                <td colspan="5" class="pt-0">
                                    <details class="rounded-box bg-error/10 p-3 text-sm">
                                        <summary class="cursor-pointer font-medium">{{ __('Mostra log di errore') }}</summary>
                                        <pre class="mt-3 overflow-x-auto whitespace-pre-wrap font-mono text-xs">{{ $job->exception ?? $job->error_message }}</pre>
                                    </details>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="py-10 text-center text-base-content/60">{{ __('Nessun job da mostrare.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
