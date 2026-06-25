<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8" data-user-access-page data-has-errors="{{ $errors->any() ? 'true' : 'false' }}">
        <x-page-header :title="__('Accessi utente')" />

        @if (session('status'))
            <div class="alert alert-success">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Esporta accessi piattaforma') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Seleziona un utente specifico oppure una categoria utenti e scarica gli accessi nel periodo scelto in formato Excel.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.user-accesses.export') }}" class="space-y-6">
                    @csrf

                    <fieldset class="rounded-box border border-base-300 p-4">
                        <legend class="px-2 text-sm font-semibold">{{ __('Perimetro export') }}</legend>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 p-4">
                                <input
                                    type="radio"
                                    name="scope_type"
                                    value="{{ \App\Http\Requests\ExportUserAccessRequest::SCOPE_USER }}"
                                    class="radio"
                                    @checked(old('scope_type', \App\Http\Requests\ExportUserAccessRequest::SCOPE_USER) === \App\Http\Requests\ExportUserAccessRequest::SCOPE_USER)
                                    data-user-access-scope
                                >
                                <span class="label-text">{{ __('Utente specifico') }}</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 p-4">
                                <input
                                    type="radio"
                                    name="scope_type"
                                    value="{{ \App\Http\Requests\ExportUserAccessRequest::SCOPE_JOB_DIMENSION }}"
                                    class="radio"
                                    @checked(old('scope_type') === \App\Http\Requests\ExportUserAccessRequest::SCOPE_JOB_DIMENSION)
                                    data-user-access-scope
                                >
                                <span class="label-text">{{ __('Categoria utenti') }}</span>
                            </label>
                        </div>
                        @error('scope_type')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="md:col-span-2" data-user-access-user-fields>
                            <label for="user_id" class="label p-0">
                                <span class="label-text font-medium">{{ __('Utente') }}</span>
                            </label>
                            <select id="user_id" name="user_id" class="select select-bordered mt-2 w-full @error('user_id') select-error @enderror">
                                <option value="">{{ __('Seleziona utente') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>
                                        {{ $user->full_name }}{{ $user->email ? ' - '.$user->email : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-6 md:col-span-2 md:grid-cols-2" data-user-access-job-fields>
                            <div>
                                <label for="job_dimension" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Dimensione lavoro') }}</span>
                                </label>
                                <select id="job_dimension" name="job_dimension" class="select select-bordered mt-2 w-full @error('job_dimension') select-error @enderror" data-user-access-job-dimension>
                                    <option value="">{{ __('Seleziona dimensione') }}</option>
                                    @foreach ($jobDimensionOptions as $key => $option)
                                        <option value="{{ $key }}" @selected(old('job_dimension') === $key)>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                                @error('job_dimension')
                                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="job_dimension_id" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Valore filtro') }}</span>
                                </label>
                                <select
                                    id="job_dimension_id"
                                    name="job_dimension_id"
                                    class="select select-bordered mt-2 w-full @error('job_dimension_id') select-error @enderror"
                                    data-user-access-job-value
                                    data-old-value="{{ old('job_dimension_id') }}"
                                >
                                    <option value="">{{ __('Seleziona valore') }}</option>
                                </select>
                                @error('job_dimension_id')
                                    <p class="mt-2 text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="date_from" class="label p-0">
                                <span class="label-text font-medium">{{ __('Da data') }}</span>
                            </label>
                            <input id="date_from" name="date_from" type="date" value="{{ old('date_from') }}" class="input input-bordered mt-2 w-full @error('date_from') input-error @enderror">
                            @error('date_from')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="date_to" class="label p-0">
                                <span class="label-text font-medium">{{ __('A data') }}</span>
                            </label>
                            <input id="date_to" name="date_to" type="date" value="{{ old('date_to') }}" class="input input-bordered mt-2 w-full @error('date_to') input-error @enderror">
                            @error('date_to')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            <x-lucide-download class="h-4 w-4" />
                            <span>{{ __('Scarica xlsx') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <div>
                    <h2 class="card-title">{{ __('Richieste recenti gruppi utenti') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Le esportazioni per categoria utenti vengono elaborate in coda e diventano scaricabili al termine.') }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Filtro') }}</th>
                                <th>{{ __('Intervallo') }}</th>
                                <th>{{ __('Richiesto da') }}</th>
                                <th>{{ __('Stato') }}</th>
                                <th>{{ __('Esito') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($userAccessExports as $userAccessExport)
                                <tr
                                    data-user-access-export-row
                                    data-terminal="{{ $userAccessExport->isTerminal() ? 'true' : 'false' }}"
                                    data-status-url="{{ route('admin.user-accesses.show', $userAccessExport) }}"
                                >
                                    <td>#{{ $userAccessExport->id }}</td>
                                    <td>{{ $userAccessExport->scopeSummary() }}</td>
                                    <td>{{ $userAccessExport->date_from?->format('d/m/Y') }} - {{ $userAccessExport->date_to?->format('d/m/Y') }}</td>
                                    <td>{{ $userAccessExport->requester?->full_name ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $userAccessExport->statusBadgeClass() }} h-fit" data-user-access-export-status-badge>
                                            {{ $userAccessExport->statusLabel() }}
                                        </span>
                                    </td>
                                    <td data-user-access-export-outcome>
                                        @if ($userAccessExport->status === \App\Models\UserAccessExport::STATUS_FAILED && $userAccessExport->error_message)
                                            <span class="text-sm text-error">{{ $userAccessExport->error_message }}</span>
                                        @elseif ($userAccessExport->status === \App\Models\UserAccessExport::STATUS_COMPLETED)
                                            <span class="text-sm text-success">{{ __('File pronto') }}</span>
                                        @else
                                            <span class="text-sm text-base-content/60">{{ __('In attesa completamento') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a
                                            href="{{ route('admin.user-accesses.download', $userAccessExport) }}"
                                            class="btn btn-sm btn-outline {{ $userAccessExport->status === \App\Models\UserAccessExport::STATUS_COMPLETED ? '' : 'hidden' }}"
                                            data-user-access-export-download
                                        >
                                            <x-lucide-download class="h-4 w-4" />
                                            <span>{{ __('Scarica') }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-base-content/70">
                                        {{ __('Nessuna richiesta export presente.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($userAccessExports->hasPages())
                    <div class="border-t border-base-300 pt-4">
                        {{ $userAccessExports->links() }}
                    </div>
                @endif
            </div>
        </div>

        @php
            $jobDimensionValuesJson = collect($jobDimensionValues)->mapWithKeys(function ($items, $dimension) {
                return [
                    $dimension => $items->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()->all(),
                ];
            })->all();
        @endphp
        <script type="application/json" data-user-access-job-values>@json($jobDimensionValuesJson)</script>
    </div>

    @vite('resources/js/pages/admin-user-accesses.js')
</x-layouts.admin>
