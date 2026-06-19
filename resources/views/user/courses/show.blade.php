<x-layouts.user>
    @php
        $completedModules = $modules->filter(fn ($module) => $module->pivot->status === 'completed')->count();
        $totalModules = $modules->count();
        $hasLanguageVerificationBlock = isset($languageVerificationBlock) && is_array($languageVerificationBlock);
        $firstResidentialStartAt = $modules
            ->first(fn ($module) => $module->type === 'res' && $module->effective_starts_at !== null)
            ?->effective_starts_at;

        $moduleTypeMeta = [
            'video' => [
                'label' => __('Video'),
                'icon' => 'lucide-clapperboard',
                'badge' => 'badge-primary',
                'badge_classes' => '',
            ],
            'res' => [
                'label' => __('Sessione in aula'),
                'icon' => 'lucide-users',
                'badge' => 'badge-accent',
                'badge_classes' => '',
            ],
            'live' => [
                'label' => __('Live'),
                'icon' => 'lucide-monitor-play',
                'badge' => 'badge-secondary',
                'badge_classes' => '',
            ],
            'scorm' => [
                'label' => __('SCORM'),
                'icon' => 'lucide-package',
                'badge' => 'badge-info',
                'badge_classes' => 'border-sky-300 text-sky-600',
            ],
            'learning_quiz' => [
                'label' => __('Quiz'),
                'icon' => 'lucide-circle-help',
                'badge' => 'badge-error',
                'badge_classes' => '',
            ],
            'satisfaction_quiz' => [
                'label' => __('Gradimento'),
                'icon' => 'lucide-message-square-heart',
                'badge' => 'badge-success',
                'badge_classes' => 'border-emerald-400 text-emerald-600',
            ],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:gap-8 sm:p-6 lg:p-8">
        <x-page-header :title="$course->title" />

        @if ($hasLanguageVerificationBlock)
            <div class="card border border-warning/40 bg-warning/10 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="text-xl font-semibold text-base-content">{{ __('Verifica conoscenza della lingua') }}</h2>
                    <p class="text-sm text-base-content/75">{{ __('Prima di poter proseguire col corso e necessario superare la verifica di conoscenza della lingua.') }}</p>
                    <div>
                        <a href="{{ route('user.courses.show', $languageVerificationBlock['verification_course']) }}" class="btn btn-primary">
                            {{ __('Vai al corso di verifica') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <h2 class="text-2xl font-semibold text-base-content">{{ __('Il tuo avanzamento') }}</h2>
                    <p class="text-base text-base-content/70">
                        {{ trans_choice(':count di :total modulo completato|:count di :total moduli completati', $completedModules, ['count' => $completedModules, 'total' => $totalModules]) }}
                    </p>
                </div>

                <div class="flex w-full max-w-md items-center gap-4">
                    <progress class="progress progress-primary h-3 flex-1" value="{{ $enrollment->completion_percentage }}" max="100"></progress>
                    <span class="text-3xl font-semibold text-primary">{{ $enrollment->completion_percentage }}%</span>
                </div>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                @if ($course->cover_image_path)
                    <div class="overflow-hidden rounded-box border border-base-300">
                        <img
                            src="{{ route('user.courses.cover-image.show', $course) }}"
                            alt="{{ __('Copertina del corso :title', ['title' => $course->title]) }}"
                            class="h-auto max-h-[28rem] w-full object-cover"
                            loading="lazy"
                        >
                    </div>
                @endif
                <h2 class="text-2xl font-semibold text-base-content">{{ __('Informazioni sul corso') }}</h2>

                @if($course->type === 'res')
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-box border border-base-300 bg-base-200/50 p-4">
                            <dt class="flex items-center gap-2 text-sm font-medium text-base-content/60">
                                <x-lucide-calendar-clock class="h-4 w-4" />
                                {{ __('Inizio') }}
                            </dt>
                            <dd class="mt-2 text-lg font-semibold text-base-content">
                                {{ $firstResidentialStartAt?->format('d/m/Y H:i') ?? __('Non disponibile') }}
                            </dd>
                        </div>

                        <div class="rounded-box border border-base-300 bg-base-200/50 p-4">
                            <dt class="flex items-center gap-2 text-sm font-medium text-base-content/60">
                                <x-lucide-map-pin class="h-4 w-4" />
                                {{ __('Sede') }}
                            </dt>
                            <dd class="mt-2 text-lg font-semibold text-base-content">
                                {{ $course->venue?->address ?? __('Non disponibile') }}
                            </dd>
                        </div>
                    </dl>
                @endif

                @if($course->categories->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach($course->categories as $courseCategory)
                            <span class="badge badge-outline badge-primary h-fit">{{ $courseCategory->name }}</span>
                        @endforeach
                    </div>
                @endif

                <p class="max-w-4xl text-base leading-8 text-base-content/80">
                    {{ $course->description }}
                </p>

                @if ($course->poster_pdf_path)
                    <div class="pt-2">
                        <a href="{{ route('user.courses.poster-pdf.download', $course) }}" class="btn btn-outline btn-primary">
                            <x-lucide-download class="h-4 w-4" />
                            {{ __('Scarica locandina') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-3xl font-semibold text-base-content">{{ __('Contenuti del corso') }}</h2>
                <span class="badge badge-lg badge-outline h-fit">{{ $totalModules }} {{ __('moduli') }}</span>
            </div>

            <div class="space-y-4">
                @foreach($modules as $module)
                    @php
                        $status = $module->pivot->status;
                        $isCompleted = $status === 'completed';
                        $isCurrentModule = (int) $enrollment->current_module_id === (int) $module->id;
                        $isRetryableQuiz = $status === 'failed'
                            && $module->type === 'learning_quiz'
                            && $module->pivot->quiz_attempts < $module->max_attempts;
                        $isAccessible = ! $hasLanguageVerificationBlock
                            && (in_array($status, ['completed', 'available', 'in_progress'], true) || $isRetryableQuiz);
                        $isCurrent = $isCurrentModule && $isAccessible;
                        $isLocked = ! $isAccessible;
                        $canReviewCompletedVideo = $isCompleted && $module->type === 'video';
                        $meta = $moduleTypeMeta[$module->type] ?? [
                            'label' => strtoupper((string) $module->type),
                            'icon' => 'lucide-shapes',
                            'badge' => 'badge-ghost',
                            'badge_classes' => '',
                        ];

                        $detail = null;

                        if ($module->isQuiz()) {
                            $detail = trans_choice(':count domanda|:count domande', $module->quiz_questions_count, ['count' => $module->quiz_questions_count]);
                        } elseif ($module->video?->duration_seconds) {
                            $detail = __(':minutes min', ['minutes' => max(1, (int) ceil($module->video->duration_seconds / 60))]);
                        } elseif ($module->effective_starts_at !== null) {
                            $detail = $module->effective_starts_at->format('d/m/Y H:i');
                        }
                    @endphp

                    <div @class([
                        'card bg-base-100 shadow-sm transition-all',
                        'border border-base-300' => ! $isCurrent,
                        'border-2 border-primary shadow-[0_0_0_1px_color-mix(in_oklab,var(--color-primary)_30%,transparent)]' => $isCurrent,
                        'opacity-60' => $isLocked,
                    ])>
                        <div class="card-body flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-start gap-4">
                                <div @class([
                                    'flex h-14 w-14 shrink-0 items-center justify-center rounded-full border text-base-content',
                                    'border-success/20 bg-success text-success-content' => $isCompleted,
                                    'border-primary/20 bg-primary text-primary-content' => $isCurrent && ! $isCompleted,
                                    'border-base-300 bg-base-200 text-base-content/50' => $isLocked,
                                    'border-info/20 bg-info/15 text-info' => ! $isCompleted && ! $isCurrent && ! $isLocked,
                                ])>
                                    @if($isCompleted)
                                        <x-lucide-check class="h-6 w-6" />
                                    @elseif($isLocked)
                                        <x-lucide-lock class="h-6 w-6" />
                                    @else
                                        <span class="text-xl font-semibold">{{ $module->order }}</span>
                                    @endif
                                </div>

                                <div class="min-w-0 space-y-3">
                                    <div class="space-y-1">
                                        <h3 class="truncate text-xl font-semibold text-base-content">{{ $module->title }}</h3>
                                        @if($module->description)
                                            <p class="line-clamp-2 text-sm text-base-content/60">{{ $module->description }}</p>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                                        <span class="badge {{ $meta['badge'] }} {{ $meta['badge_classes'] }} badge-outline gap-1.5">
                                            <x-dynamic-component :component="$meta['icon']" class="h-3.5 w-3.5" />
                                            {{ $meta['label'] }}
                                        </span>

                                        @if($detail)
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-lucide-clock class="h-4 w-4" />
                                                {{ $detail }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center justify-between gap-3 sm:justify-end">
                                @if($isCompleted)
                                    <div class="flex flex-wrap items-center justify-end gap-3">
                                        @if($canReviewCompletedVideo)
                                            <a href="{{ route('user.courses.modules.player', [$course, $module]) }}" class="btn btn-outline btn-primary btn-sm">
                                                {{ __('Rivedi') }}
                                            </a>
                                        @endif
                                    </div>
                                @elseif($isCurrent)
                                    @if($module->type === 'res' && $residentialAttendanceQrCodeDataUri)
                                        <button type="button" class="btn btn-primary gap-2" onclick="document.getElementById('residential-attendance-qr-modal').showModal()">
                                            {{ __('Certifica presenza') }}
                                            <x-lucide-chevron-right class="h-4 w-4" />
                                        </button>
                                    @else
                                        <a href="{{ route('user.courses.modules.player', [$course, $module]) }}" class="btn btn-primary gap-2">
                                            {{ __('Inizia') }}
                                            <x-lucide-chevron-right class="h-4 w-4" />
                                        </a>
                                    @endif
                                @elseif($isAccessible)
                                    @if($module->type === 'res' && $residentialAttendanceQrCodeDataUri)
                                        <button type="button" class="btn btn-outline btn-primary" onclick="document.getElementById('residential-attendance-qr-modal').showModal()">
                                            {{ __('Apri') }}
                                        </button>
                                    @else
                                        <a href="{{ route('user.courses.modules.player', [$course, $module]) }}" class="btn btn-outline btn-primary">
                                            {{ __('Apri') }}
                                        </a>
                                    @endif
                                @else
                                    <span class="inline-flex items-center gap-2 text-base font-medium text-base-content/40">
                                        <x-lucide-lock class="h-5 w-5" />
                                        {{ __('Bloccato') }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    @if($residentialAttendanceQrCodeDataUri)
        <dialog id="residential-attendance-qr-modal" class="modal">
            <div class="modal-box max-w-md">
                <h3 class="text-lg font-semibold text-base-content">{{ __('Codice QR presenza') }}</h3>
                <p class="mt-2 text-sm text-base-content/70">{{ __('Mostra questo codice QR per registrare la tua presenza al corso.') }}</p>
                <div class="mt-6 flex justify-center">
                    <img
                        src="{{ $residentialAttendanceQrCodeDataUri }}"
                        alt="{{ __('Codice QR presenza') }}"
                        class="w-full max-w-56 rounded-box border border-base-300 bg-white p-3"
                    >
                </div>
                <div class="modal-action">
                    <form method="dialog">
                        <button class="btn btn-primary">{{ __('Chiudi') }}</button>
                    </form>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button>{{ __('Chiudi') }}</button>
            </form>
        </dialog>
    @endif
</x-layouts.user>
