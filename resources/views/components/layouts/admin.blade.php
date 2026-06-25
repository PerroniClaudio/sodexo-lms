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
                @php
                    $matchesRoutePatterns = static function (array $include, array $exclude = []): bool {
                        foreach ($exclude as $pattern) {
                            if (request()->routeIs($pattern)) {
                                return false;
                            }
                        }

                        foreach ($include as $pattern) {
                            if (request()->routeIs($pattern)) {
                                return true;
                            }
                        }

                        return false;
                    };

                    $formationMenuPatterns = [
                        'admin.courses.*',
                        'admin.training-paths.*',
                        'admin.course-categories.*',
                        'admin.regia.*',
                        'admin.certificates.*',
                        'admin.videos.*',
                    ];

                    $exportsMenuPatterns = [
                        'admin.video-reports.*',
                        'admin.user-accesses.*',
                    ];

                    $registryMenuPatterns = [
                        'admin.users.*',
                        'admin.funding-entities.*',
                        'admin.partners.*',
                        'admin.job-units.*',
                    ];

                    $portalMenuPatterns = [
                        'admin.homepage.*',
                    ];

                    $importsMenuPatterns = [
                        'admin.imports.users',
                        'admin.imports.job-units',
                        'admin.imports.job-tasks',
                        'admin.imports.user-job-tasks',
                        'admin.imports.user-training-paths',
                        'admin.imports.job-task-risk-associations',
                    ];

                    $jobConfigurationMenuPatterns = [
                        'admin.job-sectors.*',
                        'admin.job-categories.*',
                        'admin.job-levels.*',
                        'admin.job-roles.*',
                        'admin.job-tasks.*',
                        'admin.nace-ateco.*',
                        'admin.risk-based-requirements.*',
                        'admin.document-types.*',
                    ];

                    $configurationMenuPatterns = [
                        'admin.language-levels.*',
                        'admin.satisfaction-survey.*',
                        ...$jobConfigurationMenuPatterns,
                    ];

                    $toolsMenuPatterns = [
                        'admin.document-conversion-jobs.*',
                        'admin.live-stream-logs.*',
                        'admin.importazioni-monitor.*',
                    ];

                    $formationMenuOpen = $matchesRoutePatterns($formationMenuPatterns);
                    $exportsMenuOpen = $matchesRoutePatterns($exportsMenuPatterns);
                    $registryMenuOpen = $matchesRoutePatterns($registryMenuPatterns);
                    $portalMenuOpen = $matchesRoutePatterns($portalMenuPatterns);
                    $importsMenuOpen = $matchesRoutePatterns($importsMenuPatterns);
                    $jobConfigurationMenuOpen = $matchesRoutePatterns($jobConfigurationMenuPatterns);
                    $configurationMenuOpen = $matchesRoutePatterns($configurationMenuPatterns);
                    $toolsMenuOpen = $matchesRoutePatterns($toolsMenuPatterns);
                    $developmentToolsMenuOpen = $matchesRoutePatterns([
                        'admin.development-tools.*',
                    ]);
                    $activeRole = session('active_role') ?? auth()->user()?->getRoleNames()->first();
                    $canAccessAdminMenu = in_array($activeRole, ['admin', 'superadmin'], true);
                    $canAccessSuperadminMenu = $activeRole === 'superadmin';
                    $isDevelopmentEnvironment = app()->environment(['local', 'development']);
                @endphp

                <ul class="admin-sidenav menu w-full gap-1">
                    @if($canAccessAdminMenu)
                        <li class="w-full">
                            <a
                                href="{{ route('admin.dashboard') }}"
                                @class([
                                    'w-full',
                                    'menu-active' => $matchesRoutePatterns(['admin.dashboard*']),
                                ])
                            >
                                <x-lucide-layout-dashboard class="mr-2 inline-block h-5 w-5" />
                                {{ __('Dashboard') }}
                            </a>
                        </li>

                        <li>
                            <details @if($formationMenuOpen) open @endif>
                                <summary>
                                    <x-lucide-graduation-cap class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Formazione') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.courses.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.courses.*'])])
                                        >
                                            <x-lucide-graduation-cap class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Corsi') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.training-paths.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.training-paths.*'])])
                                        >
                                            <x-lucide-route class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Percorsi formativi') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.course-categories.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.course-categories.*'])])
                                        >
                                            <x-lucide-tags class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Categorie corsi') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.regia.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.regia.*'])])
                                        >
                                            <x-lucide-settings class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Regia') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.certificates.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.certificates.*'])])
                                        >
                                            <x-lucide-file-text class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Attestati') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.videos.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.videos.*'])])
                                        >
                                            <x-lucide-video class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Libreria Video') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
                        </li>

                        <li>
                            <details @if($exportsMenuOpen) open @endif>
                                <summary>
                                    <x-lucide-download class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Esportazioni') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.video-reports.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.video-reports.*'])])
                                        >
                                            <x-lucide-chart-column class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Audit trail') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.user-accesses.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.user-accesses.*'])])
                                        >
                                            <x-lucide-log-in class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Accessi utente') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
                        </li>

                        <li>
                            <details @if($registryMenuOpen) open @endif>
                                <summary>
                                    <x-lucide-users class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Anagrafiche') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.users.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.users.*'])])
                                        >
                                            <x-lucide-user-round class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Utenti') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.funding-entities.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.funding-entities.*'])])
                                        >
                                            <x-lucide-building-2 class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Enti finanziatori') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.partners.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.partners.*'])])
                                        >
                                            <x-lucide-handshake class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Partner') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-units.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.job-units.*'])])
                                        >
                                            <x-lucide-map-pin class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Unità Lavorative') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
                        </li>

                        <li>
                            <details @if($importsMenuOpen) open @endif>
                                <summary>
                                    <x-lucide-file-up class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Importazioni') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.imports.users') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.imports.users'])])
                                        >
                                            <x-lucide-user-round class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Utenti') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.imports.job-units') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.imports.job-units'])])
                                        >
                                            <x-lucide-map-pin class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Unità Lavorative') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.imports.job-tasks') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.imports.job-tasks'])])
                                        >
                                            <x-lucide-clipboard-check class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Mansioni') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.imports.user-job-tasks') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.imports.user-job-tasks'])])
                                        >
                                            <x-lucide-link class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Utenti mansioni') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.imports.user-training-paths') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.imports.user-training-paths'])])
                                        >
                                            <x-lucide-route class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Utenti percorsi') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.imports.job-task-risk-associations') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.imports.job-task-risk-associations'])])
                                        >
                                            <x-lucide-shield-alert class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Mansioni rischio') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
                        </li>

                        <li>
                            <details @if($portalMenuOpen) open @endif>
                                <summary>
                                    <x-lucide-panel-top class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Portale') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.homepage.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.homepage.index'])])
                                        >
                                            <x-lucide-home class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Home page') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.homepage.privacy-policy.edit') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.homepage.privacy-policy.*'])])
                                        >
                                            <x-lucide-shield-check class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Privacy policy') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.homepage.cookie-policy.edit') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.homepage.cookie-policy.*'])])
                                        >
                                            <x-lucide-cookie class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Cookie policy') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
                        </li>
                    @endif

                    @if($canAccessSuperadminMenu)
                        <li>
                            <details @if($configurationMenuOpen) open @endif>
                                <summary>
                                    <x-lucide-sliders-horizontal class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Configurazioni') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.language-levels.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.language-levels.*'])])
                                        >
                                            <x-lucide-languages class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Livelli lingua') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.satisfaction-survey.edit') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.satisfaction-survey.*'])])
                                        >
                                            <x-lucide-clipboard-check class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Configurazione gradimento') }}
                                        </a>
                                    </li>
                                    <li>
                                        <details @if($jobConfigurationMenuOpen) open @endif>
                                            <summary>
                                                <x-lucide-briefcase class="mr-2 inline-block h-5 w-5" />
                                                {{ __('Configurazione Lavori') }}
                                            </summary>
                                            <ul>
                                                <li>
                                                    <a
                                                        href="{{ route('admin.job-sectors.index') }}"
                                                        @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.job-sectors.*'])])
                                                    >
                                                        <x-lucide-briefcase class="mr-2 inline-block h-4 w-4" />
                                                        {{ __('Settori') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a
                                                        href="{{ route('admin.job-categories.index') }}"
                                                        @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.job-categories.*'])])
                                                    >
                                                        <x-lucide-layers class="mr-2 inline-block h-4 w-4" />
                                                        {{ __('Categorie') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a
                                                        href="{{ route('admin.job-levels.index') }}"
                                                        @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.job-levels.*'])])
                                                    >
                                                        <x-lucide-chart-column class="mr-2 inline-block h-4 w-4" />
                                                        {{ __('Livelli') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a
                                                        href="{{ route('admin.job-roles.index') }}"
                                                        @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.job-roles.*'])])
                                                    >
                                                        <x-lucide-user-round class="mr-2 inline-block h-4 w-4" />
                                                        {{ __('Ruoli') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a
                                                        href="{{ route('admin.job-tasks.index') }}"
                                                        @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.job-tasks.*'])])
                                                    >
                                                        <x-lucide-clipboard-check class="mr-2 inline-block h-4 w-4" />
                                                        {{ __('Mansioni') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a
                                                        href="{{ route('admin.nace-ateco.index') }}"
                                                        @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.nace-ateco.*'])])
                                                    >
                                                        <x-lucide-search class="mr-2 inline-block h-4 w-4" />
                                                        {{ __('ATECO') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a
                                                        href="{{ route('admin.risk-based-requirements.index') }}"
                                                        @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.risk-based-requirements.*'])])
                                                    >
                                                        <x-lucide-shield-alert class="mr-2 inline-block h-4 w-4" />
                                                        {{ __('Requisiti (Rischio)') }}
                                                    </a>
                                                </li>
                                                <li>
                                                    <a
                                                        href="{{ route('admin.document-types.index') }}"
                                                        @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.document-types.*'])])
                                                    >
                                                        <x-lucide-file-text class="mr-2 inline-block h-4 w-4" />
                                                        {{ __('Tipologie documento') }}
                                                    </a>
                                                </li>
                                            </ul>
                                        </details>
                                    </li>
                                </ul>
                            </details>
                        </li>

                        <li>
                            <details @if($toolsMenuOpen) open @endif>
                                <summary>
                                    <x-lucide-wrench class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Strumenti') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.document-conversion-jobs.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.document-conversion-jobs.*'])])
                                        >
                                            <x-lucide-settings class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Debug conversioni documenti') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.importazioni-monitor.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.importazioni-monitor.*'])])
                                        >
                                            <x-lucide-file-search class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Monitor importazioni') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.live-stream-logs.index') }}"
                                            @class(['sidenav-submenu-active' => $matchesRoutePatterns(['admin.live-stream-logs.*'])])
                                        >
                                            <x-lucide-activity class="mr-2 inline-block h-5 w-5" />
                                            {{ __('Log live stream') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
                        </li>
                    @endif

                    @if($canAccessSuperadminMenu && $isDevelopmentEnvironment)
                        <li class="mt-2 border-t border-base-content/10 pt-2">
                            <details @if($developmentToolsMenuOpen) open @endif>
                                <summary @class(['menu-active' => $developmentToolsMenuOpen])>
                                    <x-lucide-flask-conical class="mr-2 inline-block h-5 w-5" />
                                    {{ __('Strumenti sviluppo') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.development-tools.reset-enrollments.index') }}"
                                            @class(['menu-active' => $matchesRoutePatterns(['admin.development-tools.reset-enrollments.*'])])
                                        >
                                            <x-lucide-rotate-ccw class="mr-2 inline-block h-4 w-4" />
                                            {{ __('Reset iscrizioni') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
                        </li>
                    @endif
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
