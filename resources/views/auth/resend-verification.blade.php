<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <div class="flex justify-center mb-4">
                            <div class="rounded-full bg-warning/10 p-3">
                                <x-lucide-mail class="h-8 w-8 text-warning" />
                            </div>
                        </div>
                        <h1 class="card-title justify-center text-2xl">
                            {{ __('Nuovo Invio Email') }}
                        </h1>
                        <p class="text-sm text-base-content/70 mt-2">
                            {{ __('Non hai ricevuto l\'email di attivazione?') }}
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success">
                            <x-lucide-circle-check class="h-5 w-5 shrink-0" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('verification.resend') }}" class="flex flex-col gap-4">
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
                                autocomplete="email"
                            >
                            @error('email')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @else
                                <label class="label">
                                    <span class="label-text-alt">{{ __('Inserisci la tua email per ricevere un nuovo link di attivazione') }}</span>
                                </label>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Invia Email') }}
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
