<x-layouts.app>
    @php
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $role = $user?->getRoleNames()->first() ?? __('nessun ruolo');
        $roleLabel = str($role)->headline();
        $avatarClass = $user?->hasAnyRole(['teacher', 'docente']) ? 'bg-secondary text-secondary-content' : 'bg-primary text-primary-content';
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

            @if ($user?->hasAnyRole(['teacher', 'docente']))
                <div class="mt-6 card rounded-box border border-warning/30 bg-base-100 shadow-sm">
                    <div class="card-body gap-4 p-6 sm:p-8">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-warning">{{ __('Debug') }}</p>
                                <h2 class="text-xl font-semibold text-base-content">{{ __('Live assegnati') }}</h2>
                                <p class="text-sm text-base-content/70">{{ __('Card temporanea per verificare corsi live, orari e accesso rapido.') }}</p>
                            </div>

                            <div class="badge badge-warning badge-outline">{{ $teacherLiveAssignments->count() }}</div>
                        </div>

                        <div class="grid gap-4">
                            @forelse ($teacherLiveAssignments as $assignment)
                                <article class="rounded-2xl border border-base-300 bg-base-200/60 p-4">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="space-y-1">
                                            <p class="text-lg font-semibold text-base-content">{{ $assignment->course_title }}</p>
                                            <p class="text-sm text-base-content/70">{{ __('Modulo live: :module', ['module' => $assignment->module_title]) }}</p>
                                            <p class="text-sm text-base-content/70">{{ __('Classe: :class', ['class' => $assignment->class_name]) }}</p>
                                            <p class="text-sm text-base-content/70">
                                                {{ __('Data: :date', ['date' => $assignment->debug_live_date]) }}
                                                ·
                                                {{ __('Orario: :time', ['time' => $assignment->debug_live_time]) }}
                                            </p>
                                        </div>

                                        <div>
                                            <a href="{{ $assignment->access_url }}" class="btn btn-sm btn-primary">
                                                {{ __('Accedi') }}
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="rounded-2xl border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
                                    {{ __('Nessun modulo live assegnato a questo teacher.') }}
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
