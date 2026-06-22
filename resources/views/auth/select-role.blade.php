@php
    $labels = [
        'user' => __('profile.options.account.user'),
        'admin' => __('profile.options.account.admin'),
        'teacher' => __('profile.options.account.teacher'),
        'docente' => __('profile.options.account.teacher'),
        'tutor' => __('profile.options.account.tutor'),
        'superadmin' => __('Superadmin'),
    ];
@endphp

<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-lg">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <h1 class="card-title justify-center text-2xl">{{ __('Scegli ruolo') }}</h1>
                        <p class="mt-2 text-sm text-base-content/70">{{ __('Seleziona area di lavoro per questa sessione.') }}</p>
                    </div>

                    <div class="grid gap-3">
                        @foreach ($roles as $role)
                            <form method="POST" action="{{ route('role.select.update') }}">
                                @csrf
                                <input type="hidden" name="role" value="{{ $role }}">
                                <button type="submit" class="btn btn-outline w-full justify-between">
                                    <span>{{ $labels[$role] ?? ucfirst($role) }}</span>
                                    <x-lucide-arrow-right class="h-4 w-4" />
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
