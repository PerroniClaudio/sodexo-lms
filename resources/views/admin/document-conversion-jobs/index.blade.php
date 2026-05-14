<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'id', 'label' => __('ID'), 'sortable' => true],
            ['key' => 'status', 'label' => __('Stato'), 'sortable' => true],
            ['key' => 'input_path', 'label' => __('File'), 'sortable' => false],
            ['key' => 'attempts', 'label' => __('Tentativi'), 'sortable' => true],
            ['key' => 'updated_at', 'label' => __('Aggiornato'), 'sortable' => true],
            ['key' => 'actions', 'label' => __('Azioni'), 'sortable' => false],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Debug conversioni documenti')">
            {{ __('Monitoraggio dei job di conversione, con retry manuale e download degli output generati.') }}
        </x-page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($statuses as $status)
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-2 p-4">
                        <span class="badge badge-outline {{ $status->badgeClass() }}">{{ $status->label() }}</span>
                        <p class="text-3xl font-semibold text-base-content">{{ $statusCounts[$status->value] ?? 0 }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <x-data-table
            :columns="$columns"
            :rows="$jobs"
            :sort="$tableSort"
            :direction="$tableDirection"
            :search="$tableSearch"
            :empty-message="__('Nessun job di conversione disponibile.')"
            :show-search="false"
        >
            <x-slot:filters>
                <form method="GET" action="{{ route('admin.document-conversion-jobs.index') }}" class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="form-control w-full">
                            <span class="label-text mb-2">{{ __('Stato') }}</span>
                            <select name="status" class="select select-bordered">
                                <option value="">{{ __('Tutti') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="form-control w-full">
                            <span class="label-text mb-2">{{ __('Ricerca') }}</span>
                            <label class="input input-bordered flex items-center gap-2">
                                <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                <input
                                    type="search"
                                    name="search"
                                    value="{{ $tableSearch }}"
                                    class="grow"
                                    placeholder="{{ __('ID, path, worker o errore') }}"
                                >
                            </label>
                        </label>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Filtra') }}
                        </button>
                        <a href="{{ route('admin.document-conversion-jobs.index') }}" class="btn btn-ghost">
                            {{ __('Reset') }}
                        </a>
                    </div>
                </form>
            </x-slot:filters>

            @foreach ($jobs as $job)
                <tr class="hover:bg-base-200">
                    <td class="align-top">
                        <div class="font-semibold">{{ $job->id }}</div>
                        <div class="text-xs text-base-content/60">{{ $job->created_at?->format('d/m/Y H:i:s') }}</div>
                    </td>
                    <td class="align-top">
                        <div class="flex flex-col gap-2">
                            <span class="badge badge-outline {{ $job->status->badgeClass() }}">
                                {{ $job->status->label() }}
                            </span>

                            @if ($job->worker_id)
                                <span class="text-xs text-base-content/60">{{ __('Worker: :worker', ['worker' => $job->worker_id]) }}</span>
                            @endif

                            @if ($job->failed_at)
                                <span class="text-xs text-base-content/60">{{ __('Fallito il :date', ['date' => $job->failed_at->format('d/m/Y H:i:s')]) }}</span>
                            @elseif ($job->completed_at)
                                <span class="text-xs text-base-content/60">{{ __('Completato il :date', ['date' => $job->completed_at->format('d/m/Y H:i:s')]) }}</span>
                            @elseif ($job->started_at)
                                <span class="text-xs text-base-content/60">{{ __('Avviato il :date', ['date' => $job->started_at->format('d/m/Y H:i:s')]) }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="align-top">
                        <div class="flex flex-col gap-2 text-sm">
                            <div>
                                <p class="font-medium">{{ __('Input') }}</p>
                                <p class="break-all text-base-content/70">{{ $job->input_disk }}:{{ $job->input_path }}</p>
                            </div>

                            @if ($job->hasGeneratedFile())
                                <div>
                                    <p class="font-medium">{{ __('Output') }}</p>
                                    <p class="break-all text-base-content/70">{{ $job->output_disk }}:{{ $job->output_path }}</p>
                                </div>
                            @endif

                            @if ($job->error_message)
                                <div class="rounded-box border border-error/30 bg-error/10 p-3 text-error-content">
                                    <p class="text-xs font-semibold uppercase tracking-wide">{{ __('Errore') }}</p>
                                    <p class="mt-1 break-words text-sm text-base-content">{{ $job->error_message }}</p>
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="align-top">
                        <div class="font-semibold">{{ $job->attempts }} / {{ $job->max_attempts }}</div>
                        @if ($job->locked_at)
                            <div class="text-xs text-base-content/60">{{ __('Lock: :date', ['date' => $job->locked_at->format('d/m/Y H:i:s')]) }}</div>
                        @endif
                    </td>
                    <td class="align-top text-sm text-base-content/70">
                        {{ $job->updated_at?->format('d/m/Y H:i:s') ?? '-' }}
                    </td>
                    <td class="align-top">
                        <div class="flex flex-wrap gap-2">
                            @if ($job->canBeRetried())
                                <form method="POST" action="{{ route('admin.document-conversion-jobs.retry', $job) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        {{ __('Ripeti') }}
                                    </button>
                                </form>
                            @endif

                            @if ($job->hasGeneratedFile())
                                <a href="{{ route('admin.document-conversion-jobs.download', $job) }}" class="btn btn-sm btn-outline">
                                    {{ __('Scarica output') }}
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
</x-layouts.admin>
