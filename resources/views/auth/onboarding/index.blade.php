<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <h1 class="card-title justify-center text-2xl">{{ __('Attiva il tuo account') }}</h1>
                        <p class="mt-2 text-sm text-base-content/70">
                            {{ __('Inserisci il tuo codice fiscale per continuare con onboarding, verifica email o recupero accesso.') }}
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-info">
                            <x-lucide-info class="h-5 w-5 shrink-0" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-error">
                            <x-lucide-circle-alert class="h-5 w-5 shrink-0" />
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('onboarding.lookup') }}" class="flex flex-col gap-4">
                        @csrf

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Codice Fiscale') }}</legend>
                            <input
                                id="fiscal_code"
                                name="fiscal_code"
                                type="text"
                                value="{{ old('fiscal_code') }}"
                                class="input w-full uppercase @error('fiscal_code') input-error @enderror"
                                style="text-transform: uppercase;"
                                oninput="this.value = this.value.toUpperCase()"
                                placeholder="{{ __('Inserisci il tuo codice fiscale') }}"
                                maxlength="16"
                                required
                                autofocus
                            >
                            @error('fiscal_code')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Continua') }}
                        </button>
                    </form>

                    <a href="{{ route('login') }}" class="btn btn-ghost">
                        <x-lucide-arrow-left class="h-4 w-4" />
                        {{ __('Torna al login') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
