<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <h1 class="card-title justify-center text-2xl">
                            {{ __('Accedi') }}
                        </h1>
                        <p class="text-sm text-base-content/70 mt-2">
                            {{ config('app.name') }} - {{ __('Area Riservata') }}
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-info">
                            <x-lucide-info class="h-5 w-5 shrink-0" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
                        @csrf

                        <div class="form-control">
                            <label for="email" class="label">
                                <span class="label-text font-medium">{{ __('Email') }}</span>
                            </label>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                class="input input-bordered @error('email') input-error @enderror"
                                required
                                autofocus
                                autocomplete="username"
                            >
                            @error('email')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label for="password" class="label">
                                <span class="label-text font-medium">{{ __('Password') }}</span>
                            </label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="input input-bordered @error('password') input-error @enderror"
                                required
                                autocomplete="current-password"
                            >
                            @error('password')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label cursor-pointer justify-start gap-3">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    class="checkbox checkbox-sm"
                                    {{ old('remember') ? 'checked' : '' }}
                                >
                                <span class="label-text">{{ __('Ricordami') }}</span>
                            </label>
                        </div>

                        <div class="flex flex-col gap-3">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Accedi') }}
                            </button>

                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="link link-hover text-sm text-center">
                                    {{ __('Password dimenticata?') }}
                                </a>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-center mt-6 text-sm text-base-content/60">
                <p>{{ __('Non hai ricevuto l\'email di attivazione?') }}</p>
                <a href="{{ route('verification.resend.form') }}" class="link link-hover">
                    {{ __('Richiedi nuovo invio') }}
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
