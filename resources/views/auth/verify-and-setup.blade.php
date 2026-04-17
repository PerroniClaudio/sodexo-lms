<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <div class="flex justify-center mb-4">
                            <div class="rounded-full bg-success/10 p-3">
                                <x-lucide-mail-check class="h-8 w-8 text-success" />
                            </div>
                        </div>
                        <h1 class="card-title justify-center text-2xl">
                            {{ __('Attiva il tuo account') }}
                        </h1>
                        <p class="text-sm text-base-content/70 mt-2">
                            {{ __('Imposta la tua password per completare l\'attivazione') }}
                        </p>
                    </div>

                    <div class="alert alert-info">
                        <x-lucide-info class="h-5 w-5 shrink-0" />
                        <div class="text-sm">
                            <p><strong>{{ __('Email:') }}</strong> {{ $user->email }}</p>
                            <p class="mt-1">{{ __('Crea una password sicura per accedere alla piattaforma.') }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ URL::signedRoute('verification.setup', ['id' => $user->id, 'hash' => $hash]) }}" class="flex flex-col gap-4">
                        @csrf

                        <div class="form-control">
                            <label for="password" class="label">
                                <span class="label-text font-medium">{{ __('Nuova Password') }}</span>
                            </label>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="input input-bordered @error('password') input-error @enderror"
                                required
                                autofocus
                                autocomplete="new-password"
                                minlength="8"
                            >
                            @error('password')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @else
                                <label class="label">
                                    <span class="label-text-alt">{{ __('Minimo 8 caratteri') }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label for="password_confirmation" class="label">
                                <span class="label-text font-medium">{{ __('Conferma Password') }}</span>
                            </label>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="input input-bordered @error('password_confirmation') input-error @enderror"
                                required
                                autocomplete="new-password"
                                minlength="8"
                            >
                            @error('password_confirmation')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary mt-2">
                            {{ __('Attiva Account') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
