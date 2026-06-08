<x-layouts.admin>
    <section class="flex min-h-full w-full flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="stat-title flex items-center gap-2">
                    <x-lucide-users class="h-4 w-4" />
                    <span>{{ __('Utenti attivi') }}</span>
                </div>
                <div class="stat-value text-primary">{{ $overview['active_learners_count'] }}</div>
            </div>
            <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="stat-title flex items-center gap-2">
                    <x-lucide-book-open class="h-4 w-4" />
                    <span>{{ __('Corsi pubblicati') }}</span>
                </div>
                <div class="stat-value text-secondary">{{ $overview['published_courses_count'] }}</div>
            </div>
            <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="stat-title flex items-center gap-2">
                    <x-lucide-badge-check class="h-4 w-4" />
                    <span>{{ __('Completamenti 30 giorni') }}</span>
                </div>
                <div class="stat-value text-success">{{ $overview['completions_last_30_days'] }}</div>
            </div>
            <div class="stat rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="stat-title flex items-center gap-2">
                    <x-lucide-gauge class="h-4 w-4" />
                    <span>{{ __('Avanzamento medio') }}</span>
                </div>
                <div class="stat-value">{{ $overview['course_completion_average'] }}%</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="card border border-base-300 bg-base-100 shadow-sm xl:col-span-2">
                <div class="card-body gap-6">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="card-title">
                            <x-lucide-chart-column class="h-5 w-5" />
                            {{ __('Andamento formazione') }}
                        </h2>
                    </div>

                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        @php
                            $enrollmentStatusLabels = [
                                \App\Models\CourseEnrollment::STATUS_ASSIGNED => __('Assegnati'),
                                \App\Models\CourseEnrollment::STATUS_IN_PROGRESS => __('In corso'),
                                \App\Models\CourseEnrollment::STATUS_COMPLETED => __('Completati'),
                                \App\Models\CourseEnrollment::STATUS_EXPIRED => __('Scaduti'),
                            ];
                        @endphp

                        @foreach ($enrollmentStatusLabels as $status => $label)
                            <div class="rounded-box bg-base-200 px-4 py-3">
                                <p class="text-xs uppercase tracking-wide text-base-content/60">{{ $label }}</p>
                                <p class="mt-2 text-2xl font-semibold">{{ $overview['enrollment_statuses'][$status] ?? 0 }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Corso') }}</th>
                                    <th class="text-right">{{ __('Iscritti') }}</th>
                                    <th class="text-right">{{ __('In corso') }}</th>
                                    <th class="text-right">{{ __('Completati') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($overview['top_courses'] as $course)
                                    <tr>
                                        <td class="font-medium">{{ $course['title'] }}</td>
                                        <td class="text-right">{{ $course['total_enrollments'] }}</td>
                                        <td class="text-right">{{ $course['in_progress_enrollments'] }}</td>
                                        <td class="text-right">{{ $course['completed_enrollments'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-base-content/60">{{ __('Nessun corso disponibile.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-5">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="card-title">
                            <x-lucide-award class="h-5 w-5" />
                            {{ __('Attestati') }}
                        </h2>
                    </div>

                    <div class="space-y-3">
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('Emessi ultimi 30 giorni') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $certificateSummary['issued_last_30_days_count'] }}</p>
                        </div>
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('In scadenza nei prossimi 30 giorni') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $certificateSummary['expiring_next_30_days_count'] }}</p>
                        </div>
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('Completati senza attestato') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $certificateSummary['completed_without_certificate_count'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="card border border-base-300 bg-base-100 shadow-sm xl:col-span-2">
                <div class="card-body gap-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="card-title">
                                <x-lucide-bell-ring class="h-5 w-5" />
                                {{ __('Utenti da sollecitare') }}
                            </h2>
                            <p class="text-sm text-base-content/70">{{ __('Utenti con corsi iniziati ma non conclusi.') }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                                <label for="inactive_days" class="text-sm text-base-content/70">{{ __('Inattivi da') }}</label>
                                <select id="inactive_days" name="inactive_days" class="select select-bordered select-sm">
                                    <option value="">{{ __('Tutti') }}</option>
                                    @foreach ([7, 15, 30] as $days)
                                        <option value="{{ $days }}" @selected($followUpInactiveDays === $days)>{{ $days }} {{ __('giorni') }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline">{{ __('Filtra') }}</button>
                            </form>

                            <a
                                href="{{ route('admin.dashboard.follow-up-users.export', array_filter(['inactive_days' => $followUpInactiveDays])) }}"
                                class="btn btn-sm btn-primary"
                            >
                                {{ __('Esporta CSV') }}
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Utente') }}</th>
                                    <th class="text-right">{{ __('Corsi aperti') }}</th>
                                    <th>{{ __('Ultimo accesso') }}</th>
                                    <th>{{ __('Corso aperto più vecchio') }}</th>
                                    <th class="text-right">{{ __('Avanzamento medio') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($followUpUsers->take(10) as $followUpUser)
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $followUpUser['full_name'] }}</div>
                                            <div class="text-xs text-base-content/60">{{ $followUpUser['email'] }}</div>
                                        </td>
                                        <td class="text-right font-semibold">{{ $followUpUser['open_courses_count'] }}</td>
                                        <td>{{ $followUpUser['last_accessed_at_label'] }}</td>
                                        <td>
                                            <div>{{ $followUpUser['oldest_open_course_title'] }}</div>
                                            <div class="text-xs text-base-content/60">{{ __('Dal :date', ['date' => $followUpUser['oldest_open_course_started_at_label']]) }}</div>
                                        </td>
                                        <td class="text-right">{{ $followUpUser['average_completion_percentage'] }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-base-content/60">{{ __('Nessun utente da sollecitare con i filtri correnti.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-5">
                    <h2 class="card-title">
                        <x-lucide-shield-alert class="h-5 w-5" />
                        {{ __('Compliance / rischio') }}
                    </h2>

                    <div class="stats stats-vertical border border-base-300 bg-base-100">
                        <div class="stat">
                            <div class="stat-title">{{ __('Utenti con requisiti mancanti') }}</div>
                            <div class="stat-value text-warning">{{ $compliance['users_with_missing_requirements'] }}</div>
                        </div>
                        <div class="stat">
                            <div class="stat-title">{{ __('Utenti con requisiti scaduti') }}</div>
                            <div class="stat-value text-error">{{ $compliance['users_with_expired_requirements'] }}</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('Utenti critici') }}</h3>
                        @forelse ($compliance['critical_users'] as $criticalUser)
                            <div class="rounded-box bg-base-200 px-4 py-3">
                                <div class="font-medium">{{ $criticalUser['full_name'] }}</div>
                                <div class="mt-1 flex flex-wrap gap-2 text-xs">
                                    <span class="badge badge-warning">{{ __('Mancanti: :count', ['count' => $criticalUser['missing_count']]) }}</span>
                                    <span class="badge badge-error">{{ __('Scaduti: :count', ['count' => $criticalUser['expired_count']]) }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/60">{{ __('Nessuna criticità rilevata.') }}</p>
                        @endforelse
                    </div>

                    <div class="space-y-3">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('Requisiti più scoperti') }}</h3>
                        @forelse ($compliance['top_requirements'] as $requirement)
                            <div class="flex items-center justify-between gap-4 rounded-box bg-base-200 px-4 py-3">
                                <span class="text-sm">{{ $requirement['name'] }}</span>
                                <span class="badge badge-outline">{{ $requirement['affected_users_count'] }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/60">{{ __('Nessun requisito scoperto.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-admin.event-calendar />
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-5">
                    <h2 class="card-title">
                        <x-lucide-triangle-alert class="h-5 w-5" />
                        {{ __('Attività da completare') }}
                    </h2>

                    <div class="space-y-3">
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('RES recenti senza documenti') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $recentResidentialWithoutDocuments->count() }}</p>
                        </div>
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('Utenti con requisiti mancanti/scaduti') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $compliance['users_with_missing_requirements'] + $compliance['users_with_expired_requirements'] }}</p>
                        </div>
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('Utenti da sollecitare') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $followUpUsers->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-5">
                    <h2 class="card-title">
                        <x-lucide-clipboard-check class="h-5 w-5" />
                        {{ __('Valutazione') }}
                    </h2>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('Superano il quiz finale senza esaurire i tentativi') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $evaluation['passed_without_exhausting_attempts_percentage'] }}%</p>
                        </div>
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('Utenti che riescono senza consumare tutti i tentativi') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $evaluation['passed_without_exhausting_attempts_count'] }}</p>
                        </div>
                        <div class="rounded-box bg-base-200 px-4 py-3">
                            <p class="text-sm text-base-content/70">{{ __('Totale iscritti con quiz finale') }}</p>
                            <p class="mt-1 text-3xl font-semibold">{{ $evaluation['final_quiz_enrollments_count'] }}</p>
                        </div>
                    </div>

                    <progress
                        class="progress progress-primary w-full"
                        value="{{ $evaluation['passed_without_exhausting_attempts_percentage'] }}"
                        max="100"
                    ></progress>

                    <div class="space-y-3">
                        @forelse ($evaluation['course_breakdown'] as $course)
                            <div class="flex items-center justify-between gap-4 rounded-box bg-base-200 px-4 py-3">
                                <div>
                                    <p class="font-medium">{{ $course['course_title'] }}</p>
                                    <p class="text-xs text-base-content/60">
                                        {{ __(':passed su :total iscritti passano senza esaurire i tentativi', [
                                            'passed' => $course['passed_without_exhausting_attempts_count'],
                                            'total' => $course['enrolled_users_count'],
                                        ]) }}
                                    </p>
                                </div>
                                <span class="badge badge-primary">{{ $course['percentage'] }}%</span>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/60">{{ __('Nessun corso con quiz finale disponibile.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-5">
                    <h2 class="card-title">
                        <x-lucide-file-warning class="h-5 w-5" />
                        {{ __('RES senza documenti') }}
                    </h2>

                    <div class="space-y-3">
                        @forelse ($recentResidentialWithoutDocuments as $resModule)
                            <div class="rounded-box bg-base-200 px-4 py-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $resModule['module_title'] }}</p>
                                        <p class="text-sm text-base-content/70">{{ $resModule['course_title'] }}</p>
                                    </div>
                                    <span class="badge badge-warning">{{ __(':count partecipanti', ['count' => $resModule['participants_count']]) }}</span>
                                </div>
                                <p class="mt-2 text-xs text-base-content/60">{{ __('Ultima sessione conclusa: :date', ['date' => $resModule['latest_schedule_end_label']]) }}</p>
                            </div>
                        @empty
                            <p class="text-sm text-base-content/60">{{ __('Nessun RES recente senza documenti caricati.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="card border border-base-300 bg-base-100 shadow-sm xl:col-span-2">
                <div class="card-body gap-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="card-title">
                                <x-lucide-clipboard-check class="h-5 w-5" />
                                {{ __('Gradimento') }}
                            </h2>
                            <p class="text-sm text-base-content/70">{{ __('Distribuzione delle risposte per ogni domanda della survey obbligatoria.') }}</p>
                        </div>
                        <span class="badge badge-outline">{{ __(':count compilazioni', ['count' => $surveySummary['submissions_count']]) }}</span>
                    </div>

                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                        @forelse ($surveySummary['questions'] as $question)
                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                <h3 class="font-semibold">{{ $question['question'] }}</h3>

                                <div class="mt-4 space-y-3">
                                    @foreach ($question['answers'] as $answer)
                                        <div class="space-y-2">
                                            <div class="flex items-center justify-between gap-4 text-sm">
                                                <span @class(['font-medium' => $answer['is_top_answer']])>{{ $answer['label'] }}</span>
                                                <span class="text-base-content/70">{{ $answer['count'] }} · {{ $answer['percentage'] }}%</span>
                                            </div>
                                            <progress
                                                @class([
                                                    'progress w-full',
                                                    'progress-primary' => $answer['is_top_answer'],
                                                    'progress-neutral' => ! $answer['is_top_answer'],
                                                ])
                                                value="{{ $answer['percentage'] }}"
                                                max="100"
                                            ></progress>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-6 text-sm text-base-content/60 xl:col-span-2">
                                {{ __('Nessun dato di gradimento disponibile.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-layouts.admin>
