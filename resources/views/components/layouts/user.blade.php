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
                {{ $slot }}
            </main>
        </div>
        <div class="drawer-side">
            @php
                $navigationRoutePrefix = str((string) request()->route()?->getName())->before('.')->toString() ?: 'user';
                $dashboardRoute = \Illuminate\Support\Facades\Route::has($navigationRoutePrefix . '.dashboard')
                    ? $navigationRoutePrefix . '.dashboard'
                    : 'user.dashboard';
                $coursesRoute = \Illuminate\Support\Facades\Route::has($navigationRoutePrefix . '.courses.index')
                    ? $navigationRoutePrefix . '.courses.index'
                    : 'user.courses.index';
                $trainingPathsRoute = $navigationRoutePrefix === 'user' || ! auth()->user()?->hasRole('user')
                    ? route('user.training-paths.index')
                    : route('role.switch', ['role' => 'user', 'redirect_route' => 'user.training-paths.index']);
                $completedCoursesRoute = $navigationRoutePrefix === 'user' || ! auth()->user()?->hasRole('user')
                    ? route('user.completed-courses.index')
                    : route('role.switch', ['role' => 'user', 'redirect_route' => 'user.completed-courses.index']);
                $profileRoute = \Illuminate\Support\Facades\Route::has($navigationRoutePrefix . '.profile.edit')
                    ? $navigationRoutePrefix . '.profile.edit'
                    : 'user.profile.edit';
            @endphp
            <label for="user-drawer" aria-label="{{ __('layout.close_sidebar') }}" class="drawer-overlay"></label>
            <div class="flex min-h-full w-72 flex-col bg-base-200 p-4 lg:h-screen lg:overflow-y-auto">
                <ul class="menu w-full gap-1">
                    <li class="w-full">
                        <a href="{{ route($dashboardRoute) }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs($dashboardRoute),
                        ])>
                            <x-lucide-layout-dashboard class="mr-2 inline-block h-5 w-5" />
                            {{ __('Dashboard') }}
                        </a>
                    </li>
                    <li class="w-full">
                        <a href="{{ route($coursesRoute) }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs($navigationRoutePrefix . '.courses.*'),
                        ])>
                            <x-lucide-graduation-cap class="inline-block mr-2 h-5 w-5" />
                            {{ __('I miei corsi') }}
                        </a>
                    </li>
                    <li class="w-full">
                        <a href="{{ $trainingPathsRoute }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs('user.training-paths.*'),
                        ])>
                            <x-lucide-list-checks class="inline-block mr-2 h-5 w-5" />
                            {{ __('Percorsi formativi') }}
                        </a>
                    </li>
                    <li class="w-full">
                        <a href="{{ $completedCoursesRoute }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs('user.completed-courses.*'),
                        ])>
                            <x-lucide-file-text class="mr-2 inline-block h-5 w-5" />
                            {{ __('Corsi completati') }}
                        </a>
                    </li>
                    <li class="w-full">
                        <a href="{{ route($profileRoute) }}" @class([
                            'w-full',
                            'menu-active' => request()->routeIs($profileRoute),
                        ])>
                            <x-lucide-user-round class="inline-block mr-2 h-5 w-5" />
                            {{ __('Profilo') }}
                        </a>
                    </li>
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
