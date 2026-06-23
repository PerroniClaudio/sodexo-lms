@php
    $userEditSections = collect([
        [
            'key' => 'user',
            'label' => __('Utente'),
            'title' => __('Dati utente'),
            'description' => __('Email e anagrafica principale.'),
            'icon' => 'lucide-user-round',
        ],
        [
            'key' => 'permissions',
            'label' => __('Permessi utente'),
            'title' => __('Permessi utente'),
            'description' => __('Ruoli applicativi assegnati.'),
            'icon' => 'lucide-shield-check',
        ],
        [
            'key' => 'residence',
            'label' => __('Residenza / domicilio'),
            'title' => __('Residenza / domicilio'),
            'description' => __('Indirizzo e recapito di riferimento.'),
            'icon' => 'lucide-map-pinned',
        ],
        [
            'key' => 'work',
            'label' => __('Lavoro'),
            'title' => __('Lavoro'),
            'description' => __('Rapporto, ruolo, mansioni e unità produttiva.'),
            'icon' => 'lucide-briefcase-business',
        ],
        [
            'key' => 'enrollments',
            'label' => __('Iscrizioni'),
            'title' => __('Iscrizioni'),
            'description' => __('Panoramica iscrizioni a percorsi formativi e corsi.'),
            'icon' => 'lucide-user-plus',
        ],
        [
            'key' => 'risk',
            'label' => __('Rischio attuale'),
            'title' => __('Rischio attuale'),
            'description' => __('Situazione rischio e requisiti soddisfatti.'),
            'icon' => 'lucide-shield-alert',
        ],
        [
            'key' => 'certificates',
            'label' => __('Certificati utente'),
            'title' => __('Certificati utente'),
            'description' => __('Archivio attestati, file e requisiti collegati.'),
            'icon' => 'lucide-file-badge',
        ],
    ]);
    $activeUserEditSection = request('section', 'user');
    if (! $userEditSections->contains(fn (array $section): bool => $section['key'] === $activeUserEditSection)) {
        $activeUserEditSection = 'user';
    }

    $activeUserEditSectionMeta = $userEditSections
        ->firstWhere('key', $activeUserEditSection);

    $courseEnrollmentStatusLabels = [
        'assigned' => __('Assegnato'),
        'in_progress' => __('In corso'),
        'completed' => __('Completato'),
        'expired' => __('Scaduto'),
        'cancelled' => __('Annullato'),
    ];
    $courseStatusLabels = \App\Models\Course::availableStatusLabels();
    $trainingPathStatusLabels = \App\Models\TrainingPath::availableStatusLabels();

    $userDisplayName = $user->full_name ?: $user->email;
@endphp

<x-layouts.admin>
    <div class="min-h-screen bg-base-100" data-admin-user-edit-page>
        <div class="grid min-h-screen w-full lg:grid-cols-[20rem_minmax(0,1fr)]">
            <aside class="min-h-screen border-r border-base-300 bg-base-200 p-4">
                <div class="sticky top-0">
                    <ul class="menu w-full gap-1">
                        @foreach ($userEditSections as $section)
                            <li class="w-full">
                                <a
                                    href="{{ route('admin.users.edit', $user).'?section='.$section['key'] }}"
                                    @class([
                                        'w-full',
                                        'menu-active' => $activeUserEditSection === $section['key'],
                                    ])
                                >
                                    <x-dynamic-component :component="$section['icon']" class="mr-2 inline-block h-5 w-5" />
                                    {{ $section['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>

            <main class="min-w-0">
                <div class="flex w-full flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                    <x-page-header :title="__('Modifica utente') . ' - ' . $userDisplayName">
                        <x-slot:actions>
                            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-square" aria-label="{{ __('Torna alla lista utenti') }}">
                                <x-lucide-arrow-left class="h-5 w-5" />
                            </a>
                        </x-slot:actions>
                    </x-page-header>

                    @if (in_array($activeUserEditSection, ['user', 'permissions', 'residence', 'work'], true))
                        @php
                            $sectionUpdateRoute = match ($activeUserEditSection) {
                                'user' => route('admin.users.user-section.update', $user),
                                'permissions' => route('admin.users.permissions-section.update', $user),
                                'residence' => route('admin.users.residence-section.update', $user),
                                'work' => route('admin.users.work-section.update', $user),
                            };
                        @endphp

                        <form
                            id="admin-user-edit-form"
                            method="POST"
                            action="{{ $sectionUpdateRoute }}"
                            class="flex flex-col gap-6"
                            data-user-edit-form
                        >
                            @csrf
                            @method('PUT')

                            <div class="card border border-base-300 bg-base-100 shadow-sm">
                                <div class="card-body gap-6">
                                    <div class="flex flex-col gap-4 border-b border-base-300 pb-6 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <h2 class="text-2xl font-semibold text-base-content">{{ $activeUserEditSectionMeta['title'] }}</h2>
                                            <p class="mt-2 text-sm text-base-content/65">{{ $activeUserEditSectionMeta['description'] }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        @if ($activeUserEditSection === 'user')
                                            <x-admin.users.forms.user-fields :user="$user" :language-levels="$languageLevels" />
                                        @endif

                                        @if ($activeUserEditSection === 'permissions')
                                            <x-admin.users.forms.permission-fields :user="$user" />
                                        @endif

                                        @if ($activeUserEditSection === 'residence')
                                            <x-admin.users.forms.residence-fields :user="$user" />
                                        @endif

                                        @if ($activeUserEditSection === 'work')
                                            <x-admin.users.forms.work-fields
                                                :user="$user"
                                                :job-categories="$jobCategories"
                                                :job-levels="$jobLevels"
                                                :job-tasks="$jobTasks"
                                                :job-roles="$jobRoles"
                                                :job-sectors="$jobSectors"
                                                :job-units="$jobUnits"
                                            />
                                        @endif
                                    </div>

                                    <div class="flex justify-end border-t border-base-300 pt-6">
                                        <button type="submit" class="btn btn-primary">
                                            <x-lucide-save class="h-4 w-4" />
                                            <span>{{ __('Salva dati') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif

                    @if ($activeUserEditSection === 'enrollments')
                        <section class="card border border-base-300 bg-base-100 shadow-sm">
                            <div class="card-body gap-6" data-user-enrollments-section>
                                <div class="flex flex-col gap-4 border-b border-base-300 pb-6 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 class="text-2xl font-semibold text-base-content">{{ __('Iscrizioni') }}</h2>
                                        <p class="mt-2 text-sm text-base-content/65">
                                            {{ __('Qui vedi le iscrizioni dell\'utente a percorsi formativi e corsi, con evidenza dei corsi collegati a un percorso.') }}
                                        </p>
                                    </div>

                                    <label class="label cursor-pointer justify-start gap-3 p-0">
                                        <input type="checkbox" class="checkbox" data-user-enrollments-show-deleted>
                                        <span class="label-text">{{ __('Mostra eliminati') }}</span>
                                    </label>
                                </div>

                                <div class="grid gap-6">
                                    <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <h3 class="text-lg font-semibold text-base-content">{{ __('Percorsi formativi') }}</h3>
                                            <span class="badge badge-outline h-fit">{{ $trainingPathEnrollments->count() }}</span>
                                        </div>

                                        <div class="mt-4 overflow-x-auto rounded-box border border-base-300">
                                            <table class="table table-zebra">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('Percorso') }}</th>
                                                        <th>{{ __('Stato percorso') }}</th>
                                                        <th>{{ __('Stato iscrizione') }}</th>
                                                        <th>{{ __('Corso corrente') }}</th>
                                                        <th>{{ __('Assegnato il') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($trainingPathEnrollments as $trainingPathEnrollment)
                                                        @php
                                                            $trainingPath = $trainingPathEnrollment->trainingPath;
                                                            $pathLabel = $trainingPath !== null
                                                                ? (($trainingPath->code ? $trainingPath->code . ' - ' : '') . $trainingPath->title)
                                                                : __('Percorso non disponibile');
                                                            $pathStatusLabel = $trainingPath !== null
                                                                ? ($trainingPathStatusLabels[$trainingPath->status] ?? $trainingPath->status)
                                                                : __('Non disponibile');
                                                        @endphp
                                                        <tr @class(['hidden' => $trainingPathEnrollment->trashed()]) data-enrollment-row @if ($trainingPathEnrollment->trashed()) data-enrollment-deleted="1" @endif>
                                                            <td>
                                                                <div class="font-medium text-base-content">{{ $pathLabel }}</div>
                                                                @if ($trainingPath !== null)
                                                                    <div class="mt-1 text-xs text-base-content/60">
                                                                        {{ __('Corsi nel percorso: :count', ['count' => $trainingPath->courses->count()]) }}
                                                                    </div>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-outline h-fit">{{ $pathStatusLabel }}</span>
                                                            </td>
                                                            <td>
                                                                @if ($trainingPathEnrollment->trashed())
                                                                    <span class="badge badge-error badge-soft h-fit">{{ __('Eliminata') }}</span>
                                                                @else
                                                                    <span class="badge badge-success badge-soft h-fit">{{ __('Attiva') }}</span>
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if ($trainingPathEnrollment->currentCourse !== null)
                                                                    {{ ($trainingPathEnrollment->currentCourse->code ? $trainingPathEnrollment->currentCourse->code . ' - ' : '') . $trainingPathEnrollment->currentCourse->title }}
                                                                @else
                                                                    <span class="text-base-content/60">{{ __('Non impostato') }}</span>
                                                                @endif
                                                            </td>
                                                            <td>{{ $trainingPathEnrollment->assigned_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="py-8 text-center text-sm text-base-content/70">
                                                                {{ __('Nessuna iscrizione a percorsi formativi.') }}
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <h3 class="text-lg font-semibold text-base-content">{{ __('Corsi') }}</h3>
                                            <span class="badge badge-outline h-fit">{{ $courseEnrollments->count() }}</span>
                                        </div>

                                        <div class="mt-4 overflow-x-auto rounded-box border border-base-300">
                                            <table class="table table-zebra">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('Corso') }}</th>
                                                        <th>{{ __('Stato corso') }}</th>
                                                        <th>{{ __('Stato iscrizione') }}</th>
                                                        <th>{{ __('Completamento') }}</th>
                                                        <th>{{ __('Appartenenza percorso') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($courseEnrollments as $courseEnrollment)
                                                        @php
                                                            $course = $courseEnrollment->course;
                                                            $courseLabel = $course !== null
                                                                ? (($course->code ? $course->code . ' - ' : '') . $course->title)
                                                                : __('Corso non disponibile');
                                                            $courseStatusLabel = $course !== null
                                                                ? ($courseStatusLabels[$course->status] ?? $course->status)
                                                                : __('Non disponibile');
                                                            $linkedPaths = collect($courseEnrollmentPathMembership[$courseEnrollment->getKey()] ?? []);
                                                            $courseEnrollmentStatusLabel = $courseEnrollmentStatusLabels[$courseEnrollment->status] ?? $courseEnrollment->status;
                                                        @endphp
                                                        <tr @class(['hidden' => $courseEnrollment->trashed()]) data-enrollment-row @if ($courseEnrollment->trashed()) data-enrollment-deleted="1" @endif>
                                                            <td>
                                                                <div class="font-medium text-base-content">{{ $courseLabel }}</div>
                                                                <div class="mt-1 text-xs text-base-content/60">
                                                                    {{ __('Assegnato il: :date', ['date' => $courseEnrollment->assigned_at?->format('d/m/Y H:i') ?? '-']) }}
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge badge-outline h-fit">{{ $courseStatusLabel }}</span>
                                                            </td>
                                                            <td>
                                                                <div class="flex flex-wrap gap-2">
                                                                    <span class="badge badge-outline h-fit">{{ $courseEnrollmentStatusLabel }}</span>
                                                                    @if ($courseEnrollment->trashed())
                                                                        <span class="badge badge-error badge-soft h-fit">{{ __('Eliminata') }}</span>
                                                                    @endif
                                                                </div>
                                                            </td>
                                                            <td>{{ (int) $courseEnrollment->completion_percentage }}%</td>
                                                            <td>
                                                                @if ($linkedPaths->isEmpty())
                                                                    <span class="badge badge-ghost h-fit">{{ __('Corso standalone') }}</span>
                                                                @else
                                                                    <div class="flex flex-wrap gap-2">
                                                                        @foreach ($linkedPaths as $linkedPath)
                                                                            <span @class([
                                                                                'badge badge-outline h-fit',
                                                                                'badge-primary' => ! ($linkedPath['is_deleted'] ?? false),
                                                                                'badge-error' => ($linkedPath['is_deleted'] ?? false),
                                                                            ])>
                                                                                {{ ($linkedPath['code'] ? $linkedPath['code'] . ' - ' : '') . $linkedPath['title'] }}
                                                                            </span>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="5" class="py-8 text-center text-sm text-base-content/70">
                                                                {{ __('Nessuna iscrizione a corsi.') }}
                                                            </td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endif

                    @if ($activeUserEditSection === 'risk')
                        @php
                            $riskSummaryCardClass = match ($riskSummary['risk_badge_class'] ?? 'badge-ghost') {
                                'badge-success' => 'border-success/30 bg-success/8',
                                'badge-warning' => 'border-warning/30 bg-warning/8',
                                'badge-error' => 'border-error/30 bg-error/8',
                                default => 'border-base-300 bg-base-100',
                            };
                        @endphp

                        <section
                            class="card border shadow-sm {{ $riskSummaryCardClass }}"
                            data-risk-summary
                            data-risk-summary-url="{{ route('admin.api.users.risk-summary', $user) }}"
                        >
                            <div class="card-body gap-6">
                                <div class="flex flex-col gap-4 border-b border-base-300 pb-6 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-2xl font-semibold text-base-content">{{ __('Rischio attuale') }}</h2>
                                    <p class="mt-2 text-sm text-base-content/65" data-risk-summary-message>{{ $riskSummary['message'] }}</p>
                                </div>

                                <div class="flex flex-col items-start gap-3 sm:items-end">
                                    <span class="badge badge-lg {{ $riskSummary['risk_badge_class'] }} h-fit" data-risk-summary-badge>
                                        {{ $riskSummary['risk_label'] ?? __('Non applicabile') }}
                                    </span>
                                    <a href="{{ route('admin.users.risk-course-selection', $user) }}" class="btn btn-primary">
                                        {{ __('Selezione manuale corso') }}
                                    </a>
                                </div>
                                </div>

                                <div class="space-y-5">
                                <div class="grid gap-3" data-risk-based-requirements-items>
                                    @forelse ($riskSummary['risk_based_requirements'] as $riskBasedRequirement)
                                        <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4">
                                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                                <div class="space-y-1">
                                                    <div class="font-semibold text-base-content">{{ $riskBasedRequirement['risk_based_requirement_name'] }}</div>
                                                    @if ($riskBasedRequirement['risk_based_requirement_description'])
                                                        <p class="text-sm text-base-content/70">{{ $riskBasedRequirement['risk_based_requirement_description'] }}</p>
                                                    @endif
                                                </div>
                                                <div class="flex flex-col items-start gap-2 md:items-end">
                                                    <span class="badge {{
                                                        $riskBasedRequirement['status'] === 'satisfied'
                                                            ? 'badge-success badge-soft'
                                                            : ($riskBasedRequirement['status'] === 'expired' ? 'badge-warning badge-soft' : 'badge-error badge-soft')
                                                    }} h-fit">
                                                        {{ $riskBasedRequirement['status_label'] }}
                                                    </span>
                                                    @if (($riskBasedRequirement['covered_by_higher_risk_certificate'] ?? false) && ($riskBasedRequirement['covering_risk_label'] ?? null))
                                                        <p class="text-sm text-base-content/70">
                                                            {{ __('Coperto da attestato valido di livello superiore: :risk', ['risk' => $riskBasedRequirement['covering_risk_label']]) }}
                                                        </p>
                                                    @endif
                                                    @if (in_array($riskBasedRequirement['status'], ['missing', 'expired'], true) && $riskBasedRequirement['required_course_validity_type_label'])
                                                        <p class="text-sm text-base-content/70">
                                                            {{ __('Richiesto: :type', ['type' => \Illuminate\Support\Str::lower($riskBasedRequirement['required_course_validity_type_label'])]) }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-base-content/70">{{ __('Nessun requisito di rischio disponibile.') }}</p>
                                    @endforelse
                                </div>

                                @if (! empty($riskSummary['future_risk_transitions']))
                                    <div class="rounded-2xl border border-dashed border-base-300 bg-base-100/80 p-4">
                                        <div class="space-y-1">
                                            <h3 class="font-semibold text-base-content">{{ __('Variazioni di rischio future') }}</h3>
                                            <p class="text-sm text-base-content/70">
                                                {{ __('Le mansioni con decorrenza o termine futuro cambiano il rischio nelle date seguenti.') }}
                                            </p>
                                        </div>

                                        <div class="mt-4 flex flex-wrap gap-3">
                                            @foreach ($riskSummary['future_risk_transitions'] as $futureRiskTransition)
                                                <div class="rounded-2xl border border-base-300 bg-base-100 px-4 py-3">
                                                    <div class="text-sm text-base-content/70">{{ $futureRiskTransition['effective_on_label'] }}</div>
                                                    <span class="mt-2 inline-flex badge {{ $futureRiskTransition['risk_badge_class'] }} h-fit">
                                                        {{ $futureRiskTransition['risk_label'] }}
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                </div>
                            </div>
                        </section>
                    @endif

                    @if ($activeUserEditSection === 'certificates')
                        <section
                            class="card border border-base-300 bg-base-100 shadow-sm"
                            data-user-certificates
                            data-index-url="{{ route('admin.api.users.certificates.index', $user) }}"
                            data-store-url="{{ route('admin.api.users.certificates.store', $user) }}"
                        >
                            <div class="card-body gap-6">
                                <div class="flex flex-col gap-4 border-b border-base-300 pb-6 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h2 class="text-2xl font-semibold text-base-content">{{ __('Certificati utente') }}</h2>
                                    <p class="mt-2 text-sm text-base-content/65">
                                        {{ __('Gestisci attestati interni, file e requisiti di rischio soddisfatti.') }}
                                    </p>
                                </div>

                                <button type="button" class="btn btn-primary" data-open-certificate-modal>
                                    <x-lucide-plus class="h-4 w-4" />
                                    <span>{{ __('Aggiungi certificato') }}</span>
                                </button>
                                </div>

                                <div class="space-y-6">
                                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                    <label class="input input-bordered flex w-full max-w-md items-center gap-2">
                                        <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                        <input type="search" class="grow" placeholder="{{ __('Cerca per nome o descrizione') }}" data-certificates-search-input>
                                    </label>

                                    <button type="button" class="btn btn-primary btn-outline" data-certificates-search-button>
                                        {{ __('Cerca') }}
                                    </button>
                                </div>

                                <div class="hidden rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/70" data-certificates-loading>
                                    {{ __('Caricamento certificati in corso...') }}
                                </div>

                                <div class="overflow-x-auto rounded-box border border-base-300">
                                    <table class="table table-zebra">
                                        <thead>
                                            <tr>
                                                <th><button type="button" class="inline-flex items-center gap-2 text-left" data-sort-key="name">{{ __('Nome') }}</button></th>
                                                <th><button type="button" class="inline-flex items-center gap-2 text-left" data-sort-key="issued_at">{{ __('Conseguimento') }}</button></th>
                                                <th><button type="button" class="inline-flex items-center gap-2 text-left" data-sort-key="expires_at">{{ __('Scadenza') }}</button></th>
                                                <th><button type="button" class="inline-flex items-center gap-2 text-left" data-sort-key="is_internal">{{ __('Tipo') }}</button></th>
                                                <th>{{ __('Tipologia documento') }}</th>
                                                <th>{{ __('File') }}</th>
                                                <th>{{ __('Requisiti di rischio') }}</th>
                                                <th class="text-right">{{ __('Azioni') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody data-certificates-tbody></tbody>
                                    </table>
                                </div>

                                <div class="rounded-box border border-dashed border-base-300 px-4 py-6 text-center text-sm text-base-content/70" data-certificates-empty>
                                    {{ __('Nessun certificato disponibile per questo utente.') }}
                                </div>

                                <div class="flex flex-col gap-4 border-t border-base-300 pt-4 lg:flex-row lg:items-center lg:justify-between">
                                    <p class="text-sm text-base-content/70" data-certificates-summary>0 certificati</p>
                                    <div class="join" data-certificates-pagination></div>
                                </div>
                                </div>
                            </div>

                            <dialog class="modal" data-certificate-modal>
                                <div class="modal-box max-w-5xl">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 class="text-lg font-semibold">{{ __('Aggiungi certificato') }}</h3>
                                            <p class="text-sm text-base-content/70">
                                                {{ __('Registra un attestato manualmente e collega i requisiti di rischio soddisfatti.') }}
                                            </p>
                                        </div>
                                        <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-certificate-modal>
                                            <x-lucide-x class="h-4 w-4" />
                                        </button>
                                    </div>

                                    <form class="mt-6 flex flex-col gap-4" data-certificate-form>
                                        <input type="hidden" name="certificate_id" value="">

                                        <div class="grid gap-4 md:grid-cols-2">
                                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                                <label class="label p-0" for="certificate_name">
                                                    <span class="label-text font-medium">{{ __('Nome certificato') }}</span>
                                                </label>
                                                <input id="certificate_name" name="name" type="text" class="input input-bordered w-full" required>
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label class="label p-0" for="certificate_issued_at">
                                                    <span class="label-text font-medium">{{ __('Data conseguimento') }}</span>
                                                </label>
                                                <input id="certificate_issued_at" name="issued_at" type="date" class="input input-bordered w-full" required>
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label class="label p-0" for="certificate_expires_at">
                                                    <span class="label-text font-medium">{{ __('Data scadenza') }}</span>
                                                </label>
                                                <input id="certificate_expires_at" name="expires_at" type="date" class="input input-bordered w-full">
                                            </div>

                                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                                <label class="label p-0" for="certificate_description">
                                                    <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                                                </label>
                                                <textarea id="certificate_description" name="description" class="textarea textarea-bordered min-h-24 w-full"></textarea>
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label class="label p-0" for="certificate_document_type_id">
                                                    <span class="label-text font-medium">{{ __('Tipologia documento') }}</span>
                                                </label>
                                                <select id="certificate_document_type_id" name="document_type_id" class="select select-bordered w-full">
                                                    <option value="">{{ __('Nessuna') }}</option>
                                                    @foreach ($documentTypes as $documentType)
                                                        <option value="{{ $documentType->id }}">{{ $documentType->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="form-control flex flex-col gap-2">
                                                <label class="label p-0" for="certificate_internal_course_id">
                                                    <span class="label-text font-medium">{{ __('Corso interno') }}</span>
                                                </label>
                                                <div
                                                    class="tooltip tooltip-top w-full"
                                                    data-certificate-internal-course-tooltip
                                                    data-tip="{{ $availableCourses->isEmpty() ? __('Nessun corso completato') : __('Nessuno') }}"
                                                >
                                                    <select
                                                        id="certificate_internal_course_id"
                                                        name="internal_course_id"
                                                        class="select select-bordered w-full max-w-full"
                                                        title="{{ $availableCourses->isEmpty() ? __('Nessun corso completato') : __('Nessuno') }}"
                                                        @disabled($availableCourses->isEmpty())
                                                    >
                                                        @if ($availableCourses->isEmpty())
                                                            <option value="" data-full-label="{{ __('Nessun corso completato') }}" title="{{ __('Nessun corso completato') }}">{{ __('Nessun corso completato') }}</option>
                                                        @else
                                                            <option value="" data-full-label="{{ __('Nessuno') }}" title="{{ __('Nessuno') }}">{{ __('Nessuno') }}</option>
                                                            @foreach ($availableCourses as $course)
                                                                @php
                                                                    $courseFullLabel = ($course['code'] ? $course['code'] . ' - ' : '') . $course['title'] . ($course['completed_at_label'] ? ' - completato il ' . $course['completed_at_label'] : '');
                                                                @endphp
                                                                <option value="{{ $course['id'] }}" data-full-label="{{ $courseFullLabel }}" title="{{ $courseFullLabel }}">
                                                                    {{ \Illuminate\Support\Str::limit($courseFullLabel, 60) }}
                                                                </option>
                                                            @endforeach
                                                        @endif
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                                <label class="label p-0" for="certificate_risk_based_requirement_ids">
                                                    <span class="label-text font-medium">{{ __('Requisiti di rischio soddisfatti') }}</span>
                                                </label>
                                                <select id="certificate_risk_based_requirement_ids" name="risk_based_requirement_ids[]" class="select select-bordered min-h-48 w-full" multiple data-risk-based-requirements-select>
                                                    @foreach ($allRiskBasedRequirements as $riskBasedRequirement)
                                                        <option value="{{ $riskBasedRequirement->id }}">{{ $riskBasedRequirement->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="form-control flex flex-col gap-3 md:col-span-2" data-certificate-files-section>
                                                <div class="flex flex-col gap-1">
                                                    <label class="label p-0" for="certificate_files">
                                                        <span class="label-text font-medium">{{ __('File certificato') }}</span>
                                                    </label>
                                                    <p class="text-sm text-base-content/70">
                                                        {{ __('I nuovi caricamenti sostituiscono quelli attivi: i file precedenti restano nel bucket ma vengono nascosti tramite soft delete.') }}
                                                    </p>
                                                </div>

                                                <div class="w-full">
                                                    <label
                                                        for="certificate_files"
                                                        class="flex min-h-36 w-full cursor-pointer flex-col items-center justify-center rounded-box border-2 border-dashed border-base-300 bg-base-100 px-6 py-8 text-center transition hover:border-primary"
                                                        data-certificate-files-dropzone
                                                    >
                                                        <x-lucide-upload class="h-8 w-8 text-base-content/50" />
                                                        <span class="mt-3 text-sm font-medium">{{ __('Trascina qui uno o più file oppure clicca per selezionarli') }}</span>
                                                        <span class="mt-2 text-xs text-base-content/60">{{ __('Formati consentiti: PDF, immagini e documenti Word. Max 50 MB per file.') }}</span>
                                                        <span class="mt-3 text-sm text-primary" data-certificate-files-selection>{{ __('Nessun file selezionato') }}</span>
                                                        <input id="certificate_files" name="files[]" type="file" class="hidden" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" data-certificate-files-input>
                                                    </label>
                                                </div>

                                                <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/70" data-certificate-files-create-hint>
                                                    {{ __('Dopo il primo salvataggio potrai vedere l’elenco completo dei file caricati, aprirli in anteprima e scaricarli.') }}
                                                </div>

                                                <div class="hidden flex-col gap-3" data-certificate-existing-files>
                                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                        <p class="text-sm text-base-content/70" data-certificate-files-summary>{{ __('Nessun file caricato.') }}</p>
                                                        <label class="label cursor-pointer justify-start gap-3 p-0">
                                                            <input type="checkbox" class="checkbox" data-certificate-files-show-deleted>
                                                            <span class="label-text">{{ __('Mostra eliminati') }}</span>
                                                        </label>
                                                    </div>

                                                    <div class="hidden rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/70" data-certificate-files-loading>
                                                        {{ __('Caricamento file in corso...') }}
                                                    </div>

                                                    <div class="overflow-x-auto rounded-box border border-base-300">
                                                        <table class="table table-zebra">
                                                            <thead>
                                                                <tr>
                                                                    <th>{{ __('Nome file') }}</th>
                                                                    <th>{{ __('Caricato il') }}</th>
                                                                    <th>{{ __('Stato') }}</th>
                                                                    <th class="text-right">{{ __('Azioni') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody data-certificate-files-tbody></tbody>
                                                        </table>
                                                    </div>

                                                    <div class="rounded-box border border-dashed border-base-300 px-4 py-6 text-center text-sm text-base-content/70" data-certificate-files-empty>
                                                        {{ __('Nessun file disponibile per questo certificato.') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex justify-end gap-3">
                                            <button type="button" class="btn btn-ghost" data-close-certificate-modal>{{ __('Annulla') }}</button>
                                            <button type="submit" class="btn btn-primary">{{ __('Salva certificato') }}</button>
                                        </div>
                                    </form>
                                </div>
                                <form method="dialog" class="modal-backdrop">
                                    <button>{{ __('Chiudi') }}</button>
                                </form>
                            </dialog>
                        </section>
                    @endif

                    <template data-risk-requirement-empty-template>
                        <p class="text-sm text-base-content/70">{{ __('Nessun requisito di rischio disponibile.') }}</p>
                    </template>

                    <template data-risk-requirement-template>
                        <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div class="space-y-1">
                                    <div class="font-semibold text-base-content" data-risk-requirement-name></div>
                                    <p class="hidden text-sm text-base-content/70" data-risk-requirement-description></p>
                                </div>
                                <div class="flex flex-col items-start gap-2 md:items-end">
                                    <span class="badge h-fit" data-risk-requirement-status></span>
                                    <p class="hidden text-sm text-base-content/70" data-risk-requirement-covering-risk></p>
                                    <p class="hidden text-sm text-base-content/70" data-risk-requirement-required-type></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template data-certificate-row-template>
                        <tr>
                            <td>
                                <div class="font-medium" data-certificate-name></div>
                            </td>
                            <td data-certificate-issued-at></td>
                            <td data-certificate-expires-at></td>
                            <td>
                                <span class="badge h-fit" data-certificate-type-badge></span>
                            </td>
                            <td>
                                <span class="hidden text-sm text-base-content/50" data-certificate-document-type-empty>-</span>
                                <span class="badge badge-outline h-fit hidden" data-certificate-document-type-badge></span>
                            </td>
                            <td>
                                <span class="text-sm text-base-content/50" data-certificate-latest-file-empty>-</span>
                                <div class="hidden space-y-1" data-certificate-latest-file>
                                    <div class="font-medium" data-certificate-latest-file-name></div>
                                    <div class="text-xs text-base-content/60" data-certificate-latest-file-summary></div>
                                </div>
                            </td>
                            <td class="max-w-md">
                                <div class="flex flex-wrap gap-1" data-certificate-risk-requirements></div>
                            </td>
                            <td>
                                <div class="ml-auto inline-grid grid-cols-[max-content_max-content] gap-2">
                                    <button type="button" class="btn btn-primary btn-sm whitespace-nowrap" data-action="edit">{{ __('Modifica') }}</button>
                                    <button type="button" class="btn btn-error btn-outline btn-sm whitespace-nowrap" data-action="delete">{{ __('Elimina') }}</button>
                                    <button type="button" class="btn btn-primary btn-outline btn-sm whitespace-nowrap hidden" data-action="preview-latest">{{ __('Anteprima') }}</button>
                                    <span class="tooltip tooltip-left hidden" data-certificate-preview-disabled data-tip="{{ __('Nessun file attivo da vedere') }}">
                                        <button type="button" class="btn btn-primary btn-outline btn-sm whitespace-nowrap" disabled>{{ __('Anteprima') }}</button>
                                    </span>
                                    <button type="button" class="btn btn-primary btn-outline btn-sm whitespace-nowrap hidden" data-action="download-latest">{{ __('Scarica') }}</button>
                                    <span class="tooltip tooltip-left hidden" data-certificate-download-disabled data-tip="{{ __('Nessun file attivo da scaricare') }}">
                                        <button type="button" class="btn btn-primary btn-outline btn-sm whitespace-nowrap" disabled>{{ __('Scarica') }}</button>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <template data-certificate-risk-requirement-badge-template>
                        <span class="badge badge-outline badge-sm h-fit"></span>
                    </template>

                    <template data-certificate-risk-requirement-empty-template>
                        <span class="text-sm text-base-content/50">-</span>
                    </template>

                    <template data-certificate-file-row-template>
                        <tr>
                            <td>
                                <div class="font-medium" data-certificate-file-name></div>
                                <div class="text-xs text-base-content/60" data-certificate-file-size></div>
                            </td>
                            <td data-certificate-file-uploaded-at></td>
                            <td>
                                <span class="badge badge-outline h-fit" data-certificate-file-status></span>
                                <div class="mt-1 hidden text-xs text-base-content/60" data-certificate-file-deleted-at></div>
                            </td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="btn btn-ghost btn-sm" data-action="preview">{{ __('Anteprima') }}</button>
                                    <button type="button" class="btn btn-ghost btn-sm" data-action="download">{{ __('Scarica') }}</button>
                                    <button type="button" class="btn btn-error btn-outline btn-sm hidden" data-action="delete">{{ __('Elimina') }}</button>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <template data-certificate-pagination-button-template>
                        <button type="button" class="join-item btn btn-sm"></button>
                    </template>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const internalCourseSelect = document.getElementById('certificate_internal_course_id');
                        const internalCourseTooltip = document.querySelector('[data-certificate-internal-course-tooltip]');

                        function syncInternalCourseTooltip() {
                            if (!internalCourseSelect || !internalCourseTooltip) {
                                return;
                            }

                            const selectedOption = internalCourseSelect.options[internalCourseSelect.selectedIndex];
                            const fullLabel = selectedOption?.dataset.fullLabel?.trim() || selectedOption?.textContent?.trim() || '';

                            internalCourseTooltip.setAttribute('data-tip', fullLabel);
                            internalCourseSelect.setAttribute('title', fullLabel);
                        }

                        if (internalCourseSelect) {
                            syncInternalCourseTooltip();
                            internalCourseSelect.addEventListener('change', syncInternalCourseTooltip);
                            internalCourseSelect.addEventListener('input', syncInternalCourseTooltip);
                            internalCourseSelect.addEventListener('focus', syncInternalCourseTooltip);
                            internalCourseSelect.addEventListener('mouseenter', syncInternalCourseTooltip);
                            internalCourseSelect.addEventListener('click', syncInternalCourseTooltip);
                            internalCourseSelect.addEventListener('keyup', syncInternalCourseTooltip);

                            // Keep tooltip/title aligned also when value is set programmatically.
                            let previousInternalCourseValue = internalCourseSelect.value;
                            setInterval(() => {
                                if (!internalCourseSelect) {
                                    return;
                                }

                                if (internalCourseSelect.value !== previousInternalCourseValue) {
                                    previousInternalCourseValue = internalCourseSelect.value;
                                    syncInternalCourseTooltip();
                                }
                            }, 250);
                        }
                    });
                </script>
            </main>
        </div>
    </div>
</x-layouts.admin>
