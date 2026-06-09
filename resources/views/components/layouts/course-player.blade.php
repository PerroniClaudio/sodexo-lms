@props([
    'course',
    'modules',
    'currentModule',
    'enrollment',
    'moduleTypeMeta',
])

@php
    $completedModules = $modules->filter(fn ($courseModule) => $courseModule->pivot->status === 'completed')->count();
    $totalModules = $modules->count();
    $authUserRole = auth()->user()?->getRoleNames()->first() ?? 'user';
    $currentModuleMeta = $moduleTypeMeta[$currentModule->type] ?? [
        'label' => strtoupper((string) $currentModule->type),
        'icon' => 'lucide-shapes',
        'badge' => 'badge-ghost',
    ];
@endphp

<x-layouts.app>
    <div class="drawer lg:drawer-open lg:h-screen lg:overflow-hidden">
        <input id="user-drawer" type="checkbox" class="drawer-toggle" />

        <div class="drawer-content flex min-h-screen flex-col bg-base-100 lg:h-screen lg:min-h-0">
            <div class="navbar border-b border-base-300 bg-base-200 px-4 shadow-sm lg:hidden">
                <div class="navbar-start">
                    <label for="user-drawer" class="btn btn-ghost drawer-button">
                        <x-lucide-menu class="h-6 w-6" />
                    </label>
                </div>
            </div>

            <main class="flex-1 lg:min-h-0 lg:overflow-y-auto">
                <div class="drawer lg:drawer-open lg:min-h-full">
                    <input id="course-drawer" type="checkbox" class="drawer-toggle" />

                    <div class="drawer-content min-w-0 p-4 sm:p-6 lg:min-h-full lg:p-8">
                        <div class="mb-4 flex items-center justify-between gap-3 lg:hidden">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ __('Moduli corso') }}</p>
                                <h1 class="text-lg font-semibold text-base-content">{{ $course->title }}</h1>
                            </div>

                            <label for="course-drawer" class="btn btn-primary drawer-button">
                                <x-lucide-panel-left-open class="h-4 w-4" />
                                {{ __('Moduli') }}
                            </label>
                        </div>

                        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
                            <div class="card border border-base-300 bg-base-100 shadow-sm">
                                <div class="card-body gap-6">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div class="space-y-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="badge {{ $currentModuleMeta['badge'] }} badge-outline gap-1.5 h-fit">
                                                    <x-dynamic-component :component="$currentModuleMeta['icon']" class="h-3.5 w-3.5" />
                                                    {{ $currentModuleMeta['label'] }}
                                                </span>
                                                <span class="badge badge-outline h-fit">{{ __('Modulo :order', ['order' => $currentModule->order]) }}</span>
                                                <span class="badge badge-outline h-fit">{{ $completedModules }}/{{ $totalModules }}</span>
                                            </div>

                                            <div>
                                                <h2 class="text-3xl font-semibold text-base-content">{{ $currentModule->title }}</h2>
                                                @if($currentModule->description)
                                                    <p class="mt-2 max-w-3xl text-sm leading-7 text-base-content/65">{{ $currentModule->description }}</p>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="shrink-0">
                                            @if (isset($headerActions))
                                                {{ $headerActions }}
                                            @endif
                                        </div>
                                    </div>

                                    <div class="h-px w-full bg-base-300"></div>

                                    {{ $slot }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="drawer-side">
                        <label for="course-drawer" aria-label="{{ __('Chiudi moduli corso') }}" class="drawer-overlay"></label>
                        <div class="flex min-h-full w-80 flex-col bg-base-300 p-4 lg:h-full lg:overflow-y-auto">
                            <div class="flex h-full min-h-full flex-col">
                                <div class="mb-4">
                                    <h2 class="text-lg font-semibold text-base-content">{{ $course->title }}</h2>
                                </div>

                                <div class="rounded-box bg-base-200/70 p-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ __('Avanzamento') }}</p>
                                            <p class="mt-1 text-sm text-base-content/70">{{ $completedModules }}/{{ $totalModules }} {{ __('moduli completati') }}</p>
                                        </div>
                                        <span class="text-2xl font-semibold text-primary">{{ $enrollment->completion_percentage }}%</span>
                                    </div>
                                    <progress class="progress progress-primary mt-3 h-2 w-full" value="{{ $enrollment->completion_percentage }}" max="100"></progress>
                                </div>

                                <div class="mt-4 rounded-box bg-base-100 p-3 shadow-sm">
                                    <ul class="list bg-transparent">
                                        <li class="px-1 pb-2 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/45">
                                            {{ __('Moduli del corso') }}
                                        </li>

                                        @foreach($modules as $courseModule)
                                            @php
                                                $status = $courseModule->pivot->status;
                                                $isCompleted = $status === 'completed';
                                                $isCurrent = (int) $courseModule->id === (int) $currentModule->id;
                                                $isRetryableQuiz = $status === 'failed'
                                                    && $courseModule->type === 'learning_quiz'
                                                    && $courseModule->pivot->quiz_attempts < $courseModule->max_attempts;
                                                $isAccessible = in_array($status, ['completed', 'available', 'in_progress'], true) || $isRetryableQuiz;
                                                $courseModuleMeta = $moduleTypeMeta[$courseModule->type] ?? [
                                                    'label' => strtoupper((string) $courseModule->type),
                                                    'icon' => 'lucide-shapes',
                                                ];
                                            @endphp

                                            <li
                                                @class([
                                                    'list-row rounded-box border border-transparent transition-colors',
                                                    'border-primary bg-primary text-primary-content shadow-sm' => $isCurrent,
                                                    'opacity-60' => ! $isAccessible,
                                                ])
                                            >
                                                <div @class([
                                                    'flex size-9 shrink-0 aspect-square items-center justify-center rounded-full bg-base-200',
                                                    'bg-accent text-accent-content' => $isCurrent,
                                                ])>
                                                    @if(! $isAccessible)
                                                        <x-lucide-lock @class([
                                                            'h-4 w-4 text-base-content/60',
                                                            'text-accent-content' => $isCurrent,
                                                        ]) />
                                                    @else
                                                        <x-dynamic-component :component="$courseModuleMeta['icon']" @class([
                                                            'h-4 w-4 text-primary',
                                                            'text-accent-content' => $isCurrent,
                                                        ]) />
                                                    @endif
                                                </div>

                                                <div class="list-col-grow">
                                                    <div @class([
                                                        'line-clamp-2 text-sm font-semibold text-base-content',
                                                        'text-primary-content' => $isCurrent,
                                                    ])>{{ $courseModule->title }}</div>
                                                    <div class="{{ $isCurrent ? 'mt-1 text-xs text-accent-content/80' : 'mt-1 text-xs text-base-content/55' }}">
                                                        {{ $courseModuleMeta['label'] }}
                                                    </div>
                                                </div>

                                                @if($isCompleted)
                                                    <span @class([
                                                        'flex size-8 items-center justify-center rounded-full bg-success/15 text-success',
                                                        'bg-accent text-accent-content' => $isCurrent,
                                                    ])>
                                                        <x-lucide-check class="h-4 w-4" />
                                                    </span>
                                                @elseif($isCurrent)
                                                    <a href="{{ route('user.courses.modules.player', [$course, $courseModule]) }}" class="btn btn-secondary btn-square btn-sm border-0">
                                                        <x-lucide-play class="h-4 w-4 fill-current" />
                                                    </a>
                                                @elseif($isAccessible)
                                                    <a href="{{ route('user.courses.modules.player', [$course, $courseModule]) }}" class="btn btn-ghost btn-square btn-sm">
                                                        <x-lucide-chevron-right class="h-4 w-4" />
                                                    </a>
                                                @else
                                                    <span class="btn btn-square btn-ghost btn-sm pointer-events-none opacity-40">
                                                        <x-lucide-lock class="h-4 w-4" />
                                                    </span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </main>
        </div>

        <div class="drawer-side">
            @php
                $user = auth()->user();
                $userName = trim($user->full_name ?: $user->name);
                $initials = collect(preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY))
                    ->take(2)
                    ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                    ->implode('');
            @endphp
            <label for="user-drawer" aria-label="{{ __('layout.close_sidebar') }}" class="drawer-overlay"></label>
            <div class="flex min-h-full w-72 flex-col bg-base-200 p-4 lg:h-screen lg:overflow-y-auto">
                <ul class="menu w-full gap-1">
                    <li class="w-full">
                        <a href="{{ route($authUserRole . '.dashboard') }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs($authUserRole . '.dashboard'),
                        ])>
                            <x-lucide-layout-dashboard class="mr-2 inline-block h-5 w-5" />
                            {{ __('Dashboard') }}
                        </a>
                    </li>
                    <li class="w-full">
                        <a href="{{ route($authUserRole . '.courses.index') }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs($authUserRole . '.courses.*'),
                        ])>
                            <x-lucide-graduation-cap class="inline-block mr-2 h-5 w-5" />
                            {{ __('I miei corsi') }}
                        </a>
                    </li>
                    <li class="w-full">
                        <a href="{{ route('user.completed-courses.index') }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs('user.completed-courses.*'),
                        ])>
                            <x-lucide-file-text class="mr-2 inline-block h-5 w-5" />
                            {{ __('Corsi completati') }}
                        </a>
                    </li>
                    <li class="w-full">
                        <a href="{{ route($authUserRole . '.profile.edit') }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs($authUserRole . '.profile.*'),
                        ])>
                            <x-lucide-user-round class="inline-block mr-2 h-5 w-5" />
                            {{ __('Profilo') }}
                        </a>
                    </li>
                </ul>
                <div class="mt-auto pt-6">
                    <div class="card border border-base-300 bg-base-100 shadow-sm">
                        <div class="card-body flex-row items-center gap-3 p-4">
                            <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary font-semibold text-primary-content">
                                {{ $initials }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-base-content">
                                    {{ $userName }}
                                </p>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm btn-circle" aria-label="{{ __('Logout') }}">
                                    <x-lucide-power class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
