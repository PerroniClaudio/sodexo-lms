<x-layouts.admin>
    @php
        $completedModules = $moduleRows->where('is_completed', true)->count();
        $totalModules = $moduleRows->count();
        $firstResidentialStartAt = $moduleRows
            ->pluck('module')
            ->first(fn (\App\Models\Module $module): bool => $module->type === \App\Models\Module::TYPE_RESIDENTIAL && $module->appointment_start_time !== null)
            ?->appointment_start_time;
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Dettaglio iscrizione corso')">
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    @if ($enrollment->user !== null)
                        <a href="{{ route('admin.users.edit', $enrollment->user) . '?section=enrollments' }}" class="btn btn-outline btn-primary">
                            {{ __('Vai all\'utente') }}
                        </a>
                    @endif
                    <a href="{{ route('admin.courses.edit', $course) }}" class="btn btn-ghost">
                        {{ __('Torna al corso') }}
                    </a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(20rem,1fr)]">
            <div class="space-y-6">
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap gap-2">
                                    <span class="badge badge-outline h-fit">{{ $course->code ? $course->code . ' - ' . $course->title : $course->title }}</span>
                                    <span class="badge badge-outline h-fit">{{ __('Stato iscrizione: :status', ['status' => $enrollment->status]) }}</span>
                                </div>
                                <h2 class="text-2xl font-semibold text-base-content">{{ $enrollment->user?->full_name ?? __('Utente non disponibile') }}</h2>
                                <p class="text-sm text-base-content/70">
                                    {{ $enrollment->user?->email ?? __('Email non disponibile') }}
                                    @if ($enrollment->user?->fiscal_code)
                                        | {{ $enrollment->user->fiscal_code }}
                                    @endif
                                </p>
                            </div>

                            <div class="min-w-72 rounded-box border border-base-300 bg-base-200/40 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ __('Avanzamento') }}</p>
                                <div class="mt-2 flex items-center justify-between gap-4">
                                    <span class="text-sm text-base-content/70">
                                        {{ trans_choice(':count di :total modulo completato|:count di :total moduli completati', $completedModules, ['count' => $completedModules, 'total' => $totalModules]) }}
                                    </span>
                                    <span class="text-3xl font-semibold text-primary">{{ (int) $enrollment->completion_percentage }}%</span>
                                </div>
                                <progress class="progress progress-primary mt-3 h-2 w-full" value="{{ (int) $enrollment->completion_percentage }}" max="100"></progress>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                <div class="text-sm text-base-content/60">{{ __('Assegnato il') }}</div>
                                <div class="mt-2 text-lg font-semibold">{{ $enrollment->assigned_at?->format('d/m/Y H:i') ?? '-' }}</div>
                            </div>
                            <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                <div class="text-sm text-base-content/60">{{ __('Ultimo accesso') }}</div>
                                <div class="mt-2 text-lg font-semibold">{{ $enrollment->last_accessed_at?->format('d/m/Y H:i') ?? '-' }}</div>
                            </div>
                            <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                <div class="text-sm text-base-content/60">{{ __('Modulo corrente') }}</div>
                                <div class="mt-2 text-lg font-semibold">{{ $enrollment->currentModule?->title ?? '-' }}</div>
                            </div>
                            <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                <div class="text-sm text-base-content/60">{{ __('Completato il') }}</div>
                                <div class="mt-2 text-lg font-semibold">{{ $enrollment->completed_at?->format('d/m/Y H:i') ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        @if ($course->cover_image_path)
                            <div class="overflow-hidden rounded-box border border-base-300">
                                <img
                                    src="{{ route('admin.courses.attachments.cover-image.preview', $course) }}"
                                    alt="{{ __('Copertina del corso :title', ['title' => $course->title]) }}"
                                    class="h-auto max-h-112 w-full object-cover"
                                    loading="lazy"
                                >
                            </div>
                        @endif

                        <h2 class="text-2xl font-semibold text-base-content">{{ __('Informazioni sul corso') }}</h2>

                        @if ($course->type === \App\Models\Module::TYPE_RESIDENTIAL)
                            <dl class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                    <dt class="text-sm text-base-content/60">{{ __('Inizio') }}</dt>
                                    <dd class="mt-2 text-lg font-semibold">{{ $firstResidentialStartAt?->format('d/m/Y H:i') ?? __('Non disponibile') }}</dd>
                                </div>
                                <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                    <dt class="text-sm text-base-content/60">{{ __('Sede') }}</dt>
                                    <dd class="mt-2 text-lg font-semibold">{{ $course->venue?->address ?? __('Non disponibile') }}</dd>
                                </div>
                            </dl>
                        @endif

                        @if ($course->categories->isNotEmpty())
                            <div class="flex flex-wrap gap-2">
                                @foreach ($course->categories as $category)
                                    <span class="badge badge-outline badge-primary h-fit">{{ $category->name }}</span>
                                @endforeach
                            </div>
                        @endif

                        <p class="text-base leading-8 text-base-content/80">{{ $course->description }}</p>
                    </div>
                </div>

                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-3xl font-semibold text-base-content">{{ __('Contenuti del corso') }}</h2>
                        <span class="badge badge-lg badge-outline h-fit">{{ $totalModules }} {{ __('moduli') }}</span>
                    </div>

                    <div class="space-y-4">
                        @foreach ($moduleRows as $row)
                            @php
                                $module = $row['module'];
                                $progress = $row['progress'];
                            @endphp

                            <div class="card border border-base-300 bg-base-100 shadow-sm">
                                <div class="card-body gap-5">
                                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                                        <div class="space-y-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="badge badge-outline h-fit">{{ __('Modulo :order', ['order' => $module->order]) }}</span>
                                                <span class="badge badge-primary badge-soft h-fit">{{ $moduleTypeLabels[$module->type] ?? $module->type }}</span>
                                                <span class="badge badge-outline h-fit">{{ $row['status_label'] }}</span>
                                                @if ($row['is_current'])
                                                    <span class="badge badge-secondary badge-soft h-fit">{{ __('Corrente') }}</span>
                                                @endif
                                                @if ($row['can_review'])
                                                    <span class="badge badge-success badge-soft h-fit">{{ __('Rivedibile') }}</span>
                                                @endif
                                            </div>

                                            <div>
                                                <h3 class="text-xl font-semibold text-base-content">{{ $module->title }}</h3>
                                                @if ($module->description)
                                                    <p class="mt-2 text-sm leading-7 text-base-content/65">{{ $module->description }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        @if ($isSuperadmin)
                                            <div class="flex flex-wrap gap-2 xl:max-w-sm xl:justify-end">
                                                @if ($row['actions']['can_reset_scorm'])
                                                    <form method="POST" action="{{ route('admin.courses.enrollments.modules.reset-scorm', [$course, $enrollment, $module]) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline btn-warning btn-sm">
                                                            {{ __('Azzera SCORM') }}
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($row['actions']['can_reset_quiz_attempts'])
                                                    <form method="POST" action="{{ route('admin.courses.enrollments.modules.reset-quiz-attempts', [$course, $enrollment, $module]) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline btn-warning btn-sm">
                                                            {{ __('Azzera tentativi') }}
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($row['actions']['can_block'])
                                                    <form method="POST" action="{{ route('admin.courses.enrollments.modules.block', [$course, $enrollment, $module]) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline btn-error btn-sm">
                                                            {{ __('Blocca') }}
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($row['actions']['can_unlock'])
                                                    <form method="POST" action="{{ route('admin.courses.enrollments.modules.unlock', [$course, $enrollment, $module]) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline btn-success btn-sm">
                                                            {{ __('Sblocca') }}
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                            <div class="text-sm text-base-content/60">{{ __('Stato dettaglio') }}</div>
                                            <div class="mt-2 text-lg font-semibold">{{ $row['detail_status_label'] }}</div>
                                        </div>
                                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                            <div class="text-sm text-base-content/60">{{ __('Ultimo accesso') }}</div>
                                            <div class="mt-2 text-lg font-semibold">{{ $row['details']['last_accessed_at'] ?? '-' }}</div>
                                        </div>
                                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                            <div class="text-sm text-base-content/60">{{ __('Iniziato il') }}</div>
                                            <div class="mt-2 text-lg font-semibold">{{ $row['details']['started_at'] ?? '-' }}</div>
                                        </div>
                                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                                            <div class="text-sm text-base-content/60">{{ __('Completato il') }}</div>
                                            <div class="mt-2 text-lg font-semibold">{{ $row['details']['completed_at'] ?? '-' }}</div>
                                        </div>
                                    </div>

                                    @if ($row['details']['type'] === 'video')
                                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Tempo totale trascorso') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['details']['time_spent_label'] }}</div>
                                            </div>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Durata video') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['details']['duration_label'] ?? '-' }}</div>
                                            </div>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Punto attuale') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['details']['current_position_label'] ?? '-' }}</div>
                                            </div>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Punto massimo raggiunto') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['details']['max_position_label'] ?? '-' }}</div>
                                            </div>
                                        </div>
                                    @elseif ($row['details']['type'] === 'learning_quiz')
                                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Tentativi') }}</div>
                                                <div class="mt-2 text-lg font-semibold">
                                                    {{ $row['details']['attempts_used'] }} / {{ $row['details']['attempts_max'] ?? __('Illimitati') }}
                                                </div>
                                            </div>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Punteggio ultimo tentativo') }}</div>
                                                <div class="mt-2 text-lg font-semibold">
                                                    @if ($row['details']['score'] !== null)
                                                        {{ $row['details']['score'] }} / {{ $row['details']['total_score'] ?? $row['details']['max_score'] }}
                                                    @else
                                                        -
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Punteggio minimo per superare') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['details']['passing_score'] ?? '-' }}</div>
                                            </div>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Esito') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['details']['passed'] ? __('Superato') : __('Non superato') }}</div>
                                            </div>
                                        </div>

                                        <div class="overflow-x-auto rounded-box border border-base-300">
                                            <table class="table table-zebra">
                                                <thead>
                                                    <tr>
                                                        <th>{{ __('Tentativo') }}</th>
                                                        <th>{{ __('Stato') }}</th>
                                                        <th>{{ __('Punteggio') }}</th>
                                                        <th>{{ __('Inviato il') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse ($row['details']['submissions'] as $submission)
                                                        <tr>
                                                            <td>{{ $loop->iteration }}</td>
                                                            <td>{{ $submission['status'] }}</td>
                                                            <td>
                                                                @if ($submission['score'] !== null)
                                                                    {{ $submission['score'] }} / {{ $submission['total_score'] }}
                                                                @else
                                                                    -
                                                                @endif
                                                            </td>
                                                            <td>{{ $submission['submitted_at'] ?? '-' }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td colspan="4" class="text-center text-sm text-base-content/70">{{ __('Nessun tentativo registrato.') }}</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    @elseif ($row['details']['type'] === 'scorm')
                                        <div class="rounded-box border border-base-300 bg-base-200/30 p-4 text-sm text-base-content/70">
                                            {{ $row['reset_count'] === 1
                                                ? __('1 azzeramento SCORM archiviato')
                                                : __(':count azzeramenti SCORM archiviati', ['count' => $row['reset_count']]) }}
                                        </div>

                                        <div class="space-y-4">
                                            @foreach ($row['details']['packages'] as $package)
                                                <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <div class="text-lg font-semibold text-base-content">{{ $package['title'] ?: __('Pacchetto SCORM') }}</div>
                                                            <div class="mt-1 flex flex-wrap gap-2 text-sm">
                                                                <span class="badge badge-outline h-fit">{{ $package['status_label'] }}</span>
                                                                <span class="badge badge-outline h-fit">{{ $package['learner_status'] }}</span>
                                                                @if ($package['version'])
                                                                    <span class="badge badge-outline h-fit">{{ __('Versione :version', ['version' => $package['version']]) }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                        <div class="text-sm text-base-content/70">
                                                            {{ __('Tempo registrato: :time', ['time' => $package['module_time_spent'] ?? '-']) }}
                                                        </div>
                                                    </div>

                                                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                                        <div>
                                                            <div class="text-sm text-base-content/60">{{ __('Avanzamento') }}</div>
                                                            <div class="mt-1 font-semibold">{{ $package['max_progress_percent'] ?? $package['progress_percent'] ?? 0 }}%</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm text-base-content/60">{{ __('Stato completamento') }}</div>
                                                            <div class="mt-1 font-semibold">{{ $package['completion_status'] ?? '-' }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm text-base-content/60">{{ __('Stato superamento') }}</div>
                                                            <div class="mt-1 font-semibold">{{ $package['success_status'] ?? '-' }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm text-base-content/60">{{ __('Posizione') }}</div>
                                                            <div class="mt-1 font-semibold">{{ $package['lesson_location'] ?? $package['max_numeric_location'] ?? '-' }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                                        <div>
                                                            <div class="text-sm text-base-content/60">{{ __('Punteggio') }}</div>
                                                            <div class="mt-1 font-semibold">{{ $package['score']['display'] ?? '-' }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm text-base-content/60">{{ __('Ultima attività') }}</div>
                                                            <div class="mt-1 font-semibold">{{ $package['session']['last_activity_label'] ?? '-' }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm text-base-content/60">{{ __('Sessione inizializzata') }}</div>
                                                            <div class="mt-1 font-semibold">{{ $package['session']['initialized_label'] ?? '-' }}</div>
                                                        </div>
                                                        <div>
                                                            <div class="text-sm text-base-content/60">{{ __('Sessione terminata') }}</div>
                                                            <div class="mt-1 font-semibold">{{ $package['session']['terminated_label'] ?? '-' }}</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Tempo tracciato') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['details']['time_spent_label'] }}</div>
                                            </div>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Data/ora modulo') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['details']['appointment_label'] ?? '-' }}</div>
                                            </div>
                                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                                <div class="text-sm text-base-content/60">{{ __('Rivedibile') }}</div>
                                                <div class="mt-2 text-lg font-semibold">{{ $row['can_review'] ? __('Sì') : __('No') }}</div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <div class="card border border-base-300 bg-base-100 shadow-sm">
                    <div class="card-body gap-4">
                        <h2 class="text-xl font-semibold">{{ __('Riepilogo rapido') }}</h2>
                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-base-content/60">{{ __('Utente') }}</dt>
                                <dd class="font-medium text-right">{{ $enrollment->user?->full_name ?? '-' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-base-content/60">{{ __('Corso') }}</dt>
                                <dd class="font-medium text-right">{{ $course->title }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-base-content/60">{{ __('Moduli completati') }}</dt>
                                <dd class="font-medium text-right">{{ $completedModules }}/{{ $totalModules }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <dt class="text-base-content/60">{{ __('Stato corso') }}</dt>
                                <dd class="font-medium text-right">{{ $course->status }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-layouts.admin>
