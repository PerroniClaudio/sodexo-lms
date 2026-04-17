<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <div class="mb-4 flex justify-center">
                            <div class="rounded-full bg-success/10 p-3">
                                <x-lucide-lock-keyhole class="h-8 w-8 text-success" />
                            </div>
                        </div>
                        <h1 class="card-title justify-center text-2xl">
                            {{ __('Imposta una nuova password') }}
                        </h1>
                        <p class="mt-2 text-sm text-base-content/70">
                            {{ __('Completa il reset inserendo email e nuova password.') }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4">
                        @csrf

                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Email') }}</legend>
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="{{ old('email', $request->email) }}"
                                class="input w-full @error('email') input-error @enderror"
                                placeholder="{{ __('Inserisci la tua email') }}"
                                required
                                autofocus
                                autocomplete="email"
                            >
                            @error('email')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Nuova password') }}</legend>
                            <input
                                id="password"
                                name="password"
                                type="password"
                                class="input w-full @error('password') input-error @enderror"
                                placeholder="{{ __('Inserisci la nuova password') }}"
                                required
                                autocomplete="new-password"
                            >
                            @error('password')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <fieldset class="fieldset">
                            <legend class="fieldset-legend">{{ __('Conferma password') }}</legend>
                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="input w-full @error('password_confirmation') input-error @enderror"
                                placeholder="{{ __('Conferma la nuova password') }}"
                                required
                                autocomplete="new-password"
                            >
                            @error('password_confirmation')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Aggiorna password') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
