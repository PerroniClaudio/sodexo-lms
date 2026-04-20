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
            <label for="my-drawer-3" aria-label="close sidebar" class="drawer-overlay"></label>
            <div class="flex min-h-full w-80 flex-col bg-base-200 p-4">
                <ul class="menu gap-1">
                    <li>
                        <a
                            href="{{ route('admin.courses.index') }}"
                            @class(['menu-active' => request()->routeIs('admin.courses.*')])
                        >
                            {{ __('Corsi') }}
                        </a>
                    </li>

                    @role('superadmin')
                        <li>
                            <details @class(['open' => request()->routeIs('admin.job-*')])>
                                <summary @class(['menu-active' => request()->routeIs('admin.job-*')])>
                                    {{ __('Configurazione Lavori') }}
                                </summary>
                                <ul>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-categories.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-categories.*')])
                                        >
                                            {{ __('Categorie') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-levels.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-levels.*')])
                                        >
                                            {{ __('Livelli') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-roles.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-roles.*')])
                                        >
                                            {{ __('Ruoli') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-sectors.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-sectors.*')])
                                        >
                                            {{ __('Settori') }}
                                        </a>
                                    </li>
                                    <li>
                                        <a
                                            href="{{ route('admin.job-units.index') }}"
                                            @class(['menu-active' => request()->routeIs('admin.job-units.*')])
                                        >
                                            {{ __('Unità Lavorative') }}
                                        </a>
                                    </li>
                                </ul>
                            </details>
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
