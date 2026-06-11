<x-layouts.app>
    <div class="drawer lg:drawer-open">
        <input id="my-drawer-3" type="checkbox" class="drawer-toggle" />
        <div class="drawer-content flex min-h-screen flex-col bg-base-100">
            <div class="navbar border-b border-base-300 bg-base-200 px-4 shadow-sm lg:hidden">
                <div class="navbar-start">
                    <label for="my-drawer-3" class="btn btn-ghost drawer-button">
                        <x-lucide-menu class="h-6 w-6" />
                    </label>
                </div>
            </div>

            <main class="flex-1">
                {{ $slot }}
            </main>
        </div>
        <div class="drawer-side">
            <label for="my-drawer-3" aria-label="{{ __('layout.close_sidebar') }}" class="drawer-overlay"></label>
            <div class="flex min-h-full w-80 flex-col bg-base-300 p-4">

                <ul class="menu w-full gap-1">
                    @role(['admin', 'superadmin'])
                        <li class="w-full">
                            <a
                                href="{{ route('admin.dashboard') }}"
                                @class([
                                    'w-full',
                                    'menu-active' => request()->routeIs('admin.dashboard*'),
                                ])
                            >
                                <x-lucide-layout-dashboard class="mr-2 inline-block h-5 w-5" />
                                {{ __('Dashboard') }}
                            </a>
                        </li>
                    @endrole

                    <li class="w-full">
                        <a
                            href="{{ route('admin.courses.index') }}"
                            @class([
                                'w-full',
                                'menu-active' => request()->routeIs('admin.courses.*'),
                            ])
                        >
                            <x-lucide-graduation-cap class="mr-2 inline-block h-5 w-5" />
                            {{ __('Corsi') }}
                        </a>
                    </li>

                    <li class="w-full">
                        <a
                            href="{{ route('admin.regia.index') }}"
                            @class([
                                'w-full',
                                'menu-active' => request()->routeIs('admin.regia.*'),
                            ])
                        >
                            <x-lucide-settings class="mr-2 inline-block h-5 w-5" />
                            {{ __('Regia') }}
                        </a>
                    </li>


                    @role(['admin', 'superadmin'])
                        <li class="w-full">
                            <a
                                href="{{ route('admin.homepage.index') }}"
                                @class([
                                    'w-full',
                                    'menu-active' => request()->routeIs('admin.homepage.*'),
                                ])
                            >
                                <x-lucide-home class="mr-2 inline-block h-5 w-5" />
                                {{ __('Home page') }}
                            </a>
                        </li>
                    @endrole

                    @role(['admin', 'superadmin'])
                        <li class="w-full">
                            <a
                                href="{{ route('admin.users.index') }}"
                                @class([
                                    'w-full',
                                    'menu-active' => request()->routeIs('admin.users.*'),
                                ])
                            >
                                <x-lucide-user-round class="mr-2 inline-block h-5 w-5" />
                                {{ __('Utenti') }}
                            </a>
                        </li>
                        <li class="w-full">
                            <a
                                href="{{ route('admin.certificates.index') }}"
                                @class([
                                    'w-full',
                                    'menu-active' => request()->routeIs('admin.certificates.*'),
                                ])
                            >
                                <x-lucide-file-text class="mr-2 inline-block h-5 w-5" />
                                {{ __('Attestati') }}
                            </a>
                        </li>
                        <li class="w-full">
                            <a
                                href="{{ route('admin.videos.index') }}"
                                @class([
                                    'w-full',
                                    'menu-active' => request()->routeIs('admin.videos.*'),
                                ])
                            >
                                <x-lucide-video class="mr-2 inline-block h-5 w-5" />
                                {{ __('Libreria Video') }}
                            </a>
                        </li>
                        <li class="w-full">
                            <a
                                href="{{ route('admin.video-reports.index') }}"
                                @class([
                                    'w-full',
                                    'menu-active' => request()->routeIs('admin.video-reports.*'),
                                ])
                            >
                                <x-lucide-chart-column class="mr-2 inline-block h-5 w-5" />
                                {{ __('Audit trail') }}
                            </a>
                        </li>
                    @endrole

                    @role('superadmin')
                        <li>
                            {{-- <details @if(request()->routeIs('admin.job-*')) open @endif> --}}
                            <details @if(request()->routeIs('admin.job-*') or request()->routeIs('admin.nace-ateco.index') or request()->routeIs('admin.risk-based-requirements.*') or request()->routeIs('admin.document-types.*')) open @endif>
                                <summary @class(['menu-active' => request()->routeIs('admin.job-*')])>
                                    <x-lucide-briefcase class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Configurazione Lavori') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-sectors.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-sectors.*')])
                                        >
                                            <x-lucide-briefcase class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Settori') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-categories.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-categories.*')])
                                        >
                                            <x-lucide-layers class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Categorie') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-levels.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-levels.*')])
                                        >
                                            <x-lucide-chart-column class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Livelli') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-roles.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-roles.*')])
                                        >
                                            <x-lucide-user-round class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Ruoli') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-tasks.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-tasks.*')])
                                        >
                                            <x-lucide-clipboard-check class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Mansioni') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-units.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-units.*')])
                                        >
                                            <x-lucide-map-pin class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Unità Lavorative') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.nace-ateco.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.nace-ateco.*')])
                                        >
                                            <x-lucide-search class="mr-2 inline-block h-4 w-4" />
                                            {{ __('ATECO') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.risk-based-requirements.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.risk-based-requirements.*')])
                                        >
                                            <x-lucide-shield-alert class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Requisiti (Rischio)') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.document-types.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.document-types.*')])
                                        >
                                            <x-lucide-file-text class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Tipologie documento') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
                        </li>
                        <li>
                            <a
                                href="{{ route('admin.document-conversion-jobs.index') }}"
                                @class(['menu-active' => request()->routeIs('admin.document-conversion-jobs.*')])
                            >
                                <x-lucide-settings class="mr-2 inline-block h-5 w-5" />
                                {{ __('Debug conversioni documenti') }}
                            </a>
                        </li>
                        <li>
                            <a
                                href="{{ route('admin.live-stream-logs.index') }}"
                                @class(['menu-active' => request()->routeIs('admin.live-stream-logs.*')])
                            >
                                <x-lucide-activity class="mr-2 inline-block h-5 w-5" />
                                {{ __('Log live stream') }}
                            </a>
                        </li>
                        <li>
                            <a
                                href="{{ route('admin.satisfaction-survey.edit') }}"
                                @class(['menu-active' => request()->routeIs('admin.satisfaction-survey.*')])
                            >
                                <x-lucide-clipboard-check class="mr-2 inline-block h-5 w-5" />
                                {{ __('Configurazione gradimento') }}
                            </a>
                        </li>
                    @endrole
                </ul>

                @auth
                    @php
                        $user = auth()->user();
                        $userName = trim($user->full_name ?: $user->name);
                        $initials = collect(preg_split('/\s+/', $userName, -1, PREG_SPLIT_NO_EMPTY))
                            ->take(2)
                            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->implode('');
                    @endphp

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
                @endauth
            </div>
        </div>
    </div>
</x-layouts.app>
