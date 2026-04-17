<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <div class="mb-4 flex justify-center">
                            <div class="rounded-full bg-info/10 p-3">
                                <x-lucide-key-round class="h-8 w-8 text-info" />
                            </div>
                        </div>
                        <h1 class="card-title justify-center text-2xl">
                            {{ __('Recupera password') }}
                        </h1>
                        <p class="mt-2 text-sm text-base-content/70">
                            {{ __('Inserisci la tua email per ricevere il link di reset.') }}
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success">
                            <x-lucide-circle-check class="h-5 w-5 shrink-0" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-4">
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
                                autocomplete="email"
                            >
                            @error('email')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Invia link di reset') }}
                        </button>
                    </form>

                    <div class="divider text-sm">{{ __('Oppure') }}</div>

                    <a href="{{ route('login') }}" class="btn btn-ghost">
                        <x-lucide-arrow-left class="h-4 w-4" />
                        {{ __('Torna al Login') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
