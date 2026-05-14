@php
    use App\Models\Course as CourseModel;
    use App\Models\Module as ModuleModel;

    $courseTypeLabel = CourseModel::availableTypeLabels()[$course->type] ?? $course->type;
@endphp

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
    <x-page-header :title="$course->title">
        <x-slot:actions>
            <a href="{{ route($routePrefix.'.courses.index') }}" class="btn btn-ghost">
                {{ __('Torna ai corsi') }}
            </a>
        </x-slot:actions>

        {{ $course->description }}
    </x-page-header>

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Tipologia') }}</p>
                    <p class="mt-2 font-medium text-base-content">{{ $courseTypeLabel }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Anno del corso') }}</p>
                    <p class="mt-2 font-medium text-base-content">{{ $course->year ?? __('Non disponibile') }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Data scadenza') }}</p>
                    <p class="mt-2 font-medium text-base-content">{{ $course->expiry_date?->format('d/m/Y') ?? __('Non disponibile') }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Questionario di gradimento') }}</p>
                    <p class="mt-2 font-medium text-base-content">{{ $course->has_satisfaction_survey ? __('Incluso') : __('Non incluso') }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Questionario obbligatorio') }}</p>
                    <p class="mt-2 font-medium text-base-content">{{ $course->satisfaction_survey_required_for_certificate ? __('Sì') : __('No') }}</p>
                </div>
                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                    <p class="text-xs uppercase tracking-wide text-base-content/50">{{ __('Moduli assegnati') }}</p>
                    <p class="mt-2 font-medium text-base-content">{{ $assignedModules->count() }} / {{ $modules->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Moduli del corso') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Panoramica completa dei moduli del corso. I moduli assegnati a te sono evidenziati.') }}
                    </p>
                </div>
                <span class="badge badge-outline">{{ $modules->count() }} {{ __('moduli') }}</span>
            </div>

            @if ($modules->isEmpty())
                <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/70">
                    {{ __('Questo corso non contiene ancora moduli.') }}
                </div>
            @else
                <div class="grid gap-4">
                    @foreach ($modules as $module)
                        <div class="rounded-box border p-4 {{ $module->is_assigned_to_staff ? 'border-primary/40 bg-primary/5' : 'border-base-300 bg-base-100' }}">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="badge badge-outline">{{ __('Modulo :order', ['order' => $module->order]) }}</span>
                                        <span class="badge badge-ghost">{{ ModuleModel::availableTypeLabels()[$module->type] ?? $module->type }}</span>
                                        @if ($module->is_assigned_to_staff)
                                            <span class="badge badge-primary">{{ __('Assegnato a te') }}</span>
                                        @else
                                            <span class="badge badge-ghost">{{ __('Non assegnato') }}</span>
                                        @endif
                                    </div>

                                    <div>
                                        <h3 class="text-lg font-semibold text-base-content">{{ $module->title }}</h3>
                                        <p class="mt-1 text-sm text-base-content/70">
                                            {{ $module->description ?: __('Nessuna descrizione disponibile.') }}
                                        </p>
                                    </div>

                                    @if ($module->is_assigned_to_staff)
                                        <p class="text-xs uppercase tracking-wide text-base-content/50">
                                            {{ __('Assegnato il :date', ['date' => $module->assigned_at_display ?? __('Data non disponibile')]) }}
                                        </p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2 lg:justify-end">
                                    @if ($module->is_assigned_to_staff && $module->type === ModuleModel::TYPE_LIVE)
                                        <a href="{{ route($routePrefix.'.live-stream.player', $module) }}" class="btn btn-primary btn-sm">
                                            {{ __('Apri live') }}
                                        </a>
                                    @endif

                                    @if ($module->is_assigned_to_staff)
                                        <button type="button" class="btn btn-secondary btn-sm" disabled>
                                            {{ __('Dettaglio modulo') }}
                                        </button>
                                        <span class="self-center text-xs text-base-content/60">{{ __('Da implementare') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div
        class="card border border-base-300 bg-base-100 shadow-sm"
        data-staff-enrollments-table
        data-enrollments-api-url="{{ route($routePrefix.'.api.courses.enrollments.index', $course) }}"
    >
        <div class="card-body gap-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Iscritti') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Elenco read-only degli iscritti al corso.') }}
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <label class="label cursor-pointer justify-start gap-3 p-0">
                    <input type="checkbox" class="checkbox" data-enrollments-show-trashed>
                    <span class="label-text">{{ __('Mostra eliminati') }}</span>
                </label>

                <div class="flex w-full max-w-xl items-center gap-2">
                    <label class="input input-bordered flex w-full items-center gap-2">
                        <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                        <input
                            type="search"
                            class="grow"
                            data-enrollments-search
                            placeholder="{{ __('Cerca nome, cognome, CF, email') }}"
                        >
                    </label>
                    <button type="button" class="btn btn-primary" data-enrollments-search-button>
                        {{ __('Cerca') }}
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-zebra w-full">
                    <thead>
                        <tr>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-sort-key="surname">
                                    {{ __('Cognome') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="surname" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="surname" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="surname" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-sort-key="name">
                                    {{ __('Nome') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="name" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="name" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="name" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-sort-key="fiscal_code">
                                    {{ __('CF') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="fiscal_code" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="fiscal_code" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="fiscal_code" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-sort-key="email">
                                    {{ __('Email') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="email" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="email" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="email" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-sort-key="status">
                                    {{ __('Stato iscrizione') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="status" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="status" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="status" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-sort-key="completion_percentage">
                                    {{ __('Completamento') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="completion_percentage" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="completion_percentage" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="completion_percentage" />
                                </button>
                            </th>
                            <th>
                                <button type="button" class="inline-flex items-center gap-2" data-sort-key="assigned_at">
                                    {{ __('Assegnato il') }}
                                    <x-lucide-chevron-up class="h-4 w-4 hidden" data-sort-icon="asc" data-sort-indicator="assigned_at" />
                                    <x-lucide-chevron-down class="h-4 w-4 hidden" data-sort-icon="desc" data-sort-indicator="assigned_at" />
                                    <x-lucide-arrow-up-down class="h-4 w-4 text-base-content/50" data-sort-icon="none" data-sort-indicator="assigned_at" />
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody data-enrollments-tbody></tbody>
                </table>
            </div>

            <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70 hidden" data-enrollments-empty>
                {{ __('Nessun iscritto presente per questo corso.') }}
            </div>

            <div class="flex flex-col gap-3 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
                <p data-enrollments-summary></p>
                <div class="join" data-enrollments-pagination></div>
            </div>

            <template data-enrollment-row-template>
                <tr class="hover:bg-base-200">
                    <td data-cell="surname"></td>
                    <td data-cell="name"></td>
                    <td data-cell="fiscal_code"></td>
                    <td data-cell="email"></td>
                    <td>
                        <span class="badge badge-outline" data-cell="status"></span>
                    </td>
                    <td data-cell="completion_percentage"></td>
                    <td data-cell="assigned_at"></td>
                </tr>
            </template>
        </div>
    </div>
</div>
