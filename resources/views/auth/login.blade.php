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
                            <span>{{ __(session('status')) }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-4">
                        @csrf

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Email') }}</legend>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email') }}"
                                class="input w-full @error('email') input-error @enderror"
                                placeholder="{{ __('Inserisci la tua email') }}"
                                required
                                autofocus
                                autocomplete="username"
                            >
                            @error('email')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Password') }}</legend>
                            <label class="input w-full @error('password') input-error @enderror">
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    class="grow"
                                    placeholder="{{ __('Inserisci la tua password') }}"
                                    required
                                    autocomplete="current-password"
                                    data-password-input
                                >
                                <label class="swap swap-rotate cursor-pointer text-base-content/60">
                                    <input
                                        type="checkbox"
                                        class="sr-only"
                                        data-password-toggle
                                        data-show-label="{{ __('Mostra password') }}"
                                        data-hide-label="{{ __('Nascondi password') }}"
                                        aria-label="{{ __('Mostra password') }}"
                                    >
                                    <x-lucide-eye class="swap-off h-4 w-4" data-password-icon="show" />
                                    <x-lucide-eye-off class="swap-on h-4 w-4" data-password-icon="hide" />
                                </label>
                            </label>
                            @error('password')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

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

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const passwordInput = document.querySelector('[data-password-input]');
            const toggleInput = document.querySelector('[data-password-toggle]');

            if (! passwordInput || ! toggleInput) {
                return;
            }

            const showLabel = toggleInput.dataset.showLabel;
            const hideLabel = toggleInput.dataset.hideLabel;

            toggleInput.addEventListener('change', () => {
                const passwordIsVisible = toggleInput.checked;

                passwordInput.type = passwordIsVisible ? 'text' : 'password';
                toggleInput.setAttribute('aria-label', passwordIsVisible ? hideLabel : showLabel);
            });
        });
    </script>
</x-layouts.app>
