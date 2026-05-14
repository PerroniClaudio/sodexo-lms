<x-layouts.app>
    @php
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $role = $user?->getRoleNames()->first() ?? __('nessun ruolo');
        $roleLabel = str($role)->headline();
        $avatarClass = $user?->hasRole('teacher') ? 'bg-secondary text-secondary-content' : 'bg-primary text-primary-content';
    @endphp

    <section class="min-h-screen bg-base-200 px-4 py-10 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-4xl">
            <div class="card rounded-box border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-6 p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div class="flex items-center gap-5">
                        <div class="avatar">
                            <div class="flex h-20 w-20 items-center justify-center rounded-full text-2xl font-semibold {{ $avatarClass }}">
                                <span>{{ str($user->name)->substr(0, 1) }}{{ str($user->surname)->substr(0, 1) }}</span>
                            </div>
                        </div>

                        <div>
                            <h1 class="text-3xl font-semibold text-base-content">
                                {{ $user->name }} {{ $user->surname }}
                            </h1>
                            <p class="mt-2 text-lg text-base-content/70">
                                {{ __('Ruolo: :role', ['role' => $roleLabel]) }}
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <button type="submit" class="btn btn-neutral">
                            {{ __('Logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
