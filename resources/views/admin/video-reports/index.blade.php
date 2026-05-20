<x-layouts.admin>
    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-video-reports-page
        data-has-errors="{{ $errors->any() ? 'true' : 'false' }}"
    >
        <x-page-header :title="__('Audit trail')">
            <x-slot:actions>
                <button type="button" class="btn btn-primary" data-open-video-report-modal>
                    <x-lucide-download class="h-4 w-4" />
                    <span>{{ __('Richiedi export') }}</span>
                </button>
            </x-slot:actions>
        </x-page-header>

        @if (session('status'))
            <div class="alert alert-success">
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <div>
                    <h2 class="card-title">{{ __('Richieste recenti') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Monitora generazione report e scarica Excel quando disponibile.') }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Tipo') }}</th>
                                <th>{{ __('Filtro') }}</th>
                                <th>{{ __('Intervallo') }}</th>
                                <th>{{ __('Richiesto da') }}</th>
                                <th>{{ __('Stato') }}</th>
                                <th>{{ __('Esito') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($videoReportRequests as $videoReportRequest)
                                <tr
                                    data-video-report-row
                                    data-terminal="{{ $videoReportRequest->isTerminal() ? 'true' : 'false' }}"
                                    data-status-url="{{ route('admin.video-reports.show', $videoReportRequest) }}"
                                >
                                    <td>#{{ $videoReportRequest->id }}</td>
                                    <td>
                                        <span class="badge badge-outline">{{ $videoReportRequest->reportTypeLabel() }}</span>
                                    </td>
                                    <td>
                                        <div class="space-y-1">
                                            <p class="font-medium">{{ $videoReportRequest->scopeSummary() }}</p>
                                            @if ($videoReportRequest->scope_type === \App\Models\VideoReportRequest::SCOPE_JOB_DIMENSION)
                                                <p class="text-xs text-base-content/60">
                                                    {{ __('Dimensione: :dimension', ['dimension' => $jobDimensionOptions[$videoReportRequest->job_dimension]['label'] ?? $videoReportRequest->job_dimension]) }}
                                                </p>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $videoReportRequest->date_from?->format('d/m/Y') }} - {{ $videoReportRequest->date_to?->format('d/m/Y') }}</td>
                                    <td>{{ $videoReportRequest->requester?->full_name ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $videoReportRequest->statusBadgeClass() }}" data-video-report-status-badge>
                                            {{ $videoReportRequest->statusLabel() }}
                                        </span>
                                    </td>
                                    <td data-video-report-outcome>
                                        @if ($videoReportRequest->status === \App\Models\VideoReportRequest::STATUS_FAILED && $videoReportRequest->error_message)
                                            <span class="text-sm text-error">{{ $videoReportRequest->error_message }}</span>
                                        @elseif ($videoReportRequest->status === \App\Models\VideoReportRequest::STATUS_COMPLETED)
                                            <span class="text-sm text-success">{{ __('File pronto') }}</span>
                                        @else
                                            <span class="text-sm text-base-content/60">{{ __('In attesa completamento') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <a
                                            href="{{ route('admin.video-reports.download', $videoReportRequest) }}"
                                            class="btn btn-sm btn-outline {{ $videoReportRequest->status === \App\Models\VideoReportRequest::STATUS_COMPLETED ? '' : 'hidden' }}"
                                            data-video-report-download
                                        >
                                            <x-lucide-download class="h-4 w-4" />
                                            <span>{{ __('Scarica') }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-8 text-center text-base-content/70">
                                        {{ __('Nessuna richiesta report presente.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($videoReportRequests->hasPages())
                    <div class="border-t border-base-300 pt-4">
                        {{ $videoReportRequests->links() }}
                    </div>
                @endif
            </div>
        </div>

        <dialog class="modal" data-video-report-modal>
            <div class="modal-box max-w-3xl">
                <form method="dialog">
                    <button type="submit" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2" data-close-video-report-modal>✕</button>
                </form>

                <div class="space-y-2">
                    <h2 class="text-xl font-semibold">{{ __('Richiedi audit trail') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Scegli se esportare l’audit trail video o live per corsi FAD e FAD Asincrona.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.video-reports.store') }}" class="mt-6 space-y-6">
                    @csrf

                    <fieldset class="rounded-box border border-base-300 p-4">
                        <legend class="px-2 text-sm font-semibold">{{ __('Tipo audit trail') }}</legend>
                        <div class="grid gap-4 md:grid-cols-2">
                            @foreach ($reportTypeOptions as $key => $option)
                                <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 p-4">
                                    <input
                                        type="radio"
                                        name="report_type"
                                        value="{{ $key }}"
                                        class="radio"
                                        @checked(old('report_type', \App\Models\VideoReportRequest::REPORT_TYPE_VIDEO) === $key)
                                    >
                                    <span class="label-text">{{ $option['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('report_type')
                            <p class="mt-2 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <fieldset class="rounded-box border border-base-300 p-4">
                        <legend class="px-2 text-sm font-semibold">{{ __('Perimetro report') }}</legend>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 p-4">
                                <input
                                    type="radio"
                                    name="scope_type"
                                    value="{{ \App\Models\VideoReportRequest::SCOPE_COURSE }}"
                                    class="radio"
                                    @checked(old('scope_type', \App\Models\VideoReportRequest::SCOPE_COURSE) === \App\Models\VideoReportRequest::SCOPE_COURSE)
                                    data-video-report-scope
                                >
                                <span class="label-text">{{ __('Per corso') }}</span>
                            </label>
                            <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 p-4">
                                <input
                                    type="radio"
                                    name="scope_type"
                                    value="{{ \App\Models\VideoReportRequest::SCOPE_JOB_DIMENSION }}"
                                    class="radio"
                                    @checked(old('scope_type') === \App\Models\VideoReportRequest::SCOPE_JOB_DIMENSION)
                                    data-video-report-scope
                                >
                                <span class="label-text">{{ __('Per categoria utenti') }}</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div data-video-report-course-fields>
                            <label for="course_id" class="label p-0">
                                <span class="label-text font-medium">{{ __('Corso') }}</span>
                            </label>
                            <p class="mt-2 text-xs text-base-content/60">{{ __('Sono selezionabili solo corsi FAD e FAD Asincrona.') }}</p>
                            <select id="course_id" name="course_id" class="select select-bordered mt-2 w-full @error('course_id') select-error @enderror">
                                <option value="">{{ __('Seleziona corso') }}</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>{{ $course->title }}</option>
                                @endforeach
                            </select>
                            @error('course_id')
                                <p class="mt-2 text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid gap-6 md:col-span-2 md:grid-cols-2" data-video-report-job-fields>
                            <div>
                                <label for="job_dimension" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Dimensione lavoro') }}</span>
                                </label>
                                <select id="job_dimension" name="job_dimension" class="select select-bordered mt-2 w-full @error('job_dimension') select-error @enderror" data-video-report-job-dimension>
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
                                    data-video-report-job-value
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

                    <div class="flex justify-end gap-3">
                        <button type="button" class="btn btn-ghost" data-close-video-report-modal>{{ __('Annulla') }}</button>
                        <button type="submit" class="btn btn-primary" data-modal-submit-loading>
                            <x-lucide-download class="h-4 w-4" />
                            <span>{{ __('Accoda export') }}</span>
                        </button>
                    </div>
                </form>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit">{{ __('Chiudi') }}</button>
            </form>
        </dialog>

        @php
            $jobDimensionValuesJson = collect($jobDimensionValues)->mapWithKeys(function ($items, $dimension) {
                return [
                    $dimension => $items->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])->values()->all(),
                ];
            })->all();
        @endphp
        <script type="application/json" data-video-report-job-values>@json($jobDimensionValuesJson)</script>
    </div>

    @vite('resources/js/pages/admin-video-reports.js')
</x-layouts.admin>
