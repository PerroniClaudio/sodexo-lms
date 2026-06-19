<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <h1 class="card-title justify-center text-2xl">{{ __('Onboarding account') }}</h1>
                        <p class="mt-2 text-sm text-base-content/70">
                            {{ __('Codice fiscale: :fiscalCode', ['fiscalCode' => $user->fiscal_code]) }}
                        </p>
                    </div>

                    @if (session('status'))
                        <div class="alert alert-success">
                            <x-lucide-circle-check class="h-5 w-5 shrink-0" />
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-error">
                            <x-lucide-circle-alert class="h-5 w-5 shrink-0" />
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    @if ($mode === 'collect_email')
                        <form method="POST" action="{{ route('onboarding.email.store') }}" class="flex flex-col gap-4">
                            @csrf

                            <fieldset class="fieldset">
                                <legend class="fieldset-legend">{{ __('Email') }}</legend>
                                <input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email', $user->email ?? '') }}"
                                    class="input w-full @error('email') input-error @enderror"
                                    placeholder="{{ __('Inserisci la tua email') }}"
                                    required
                                    autofocus
                                >
                                @error('email')
                                    <p class="label text-error">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            <button type="submit" class="btn btn-primary">
                                {{ __('Invia email di verifica') }}
                            </button>
                        </form>

                        <a href="{{ route('onboarding.index') }}" class="btn btn-ghost">
                            <x-lucide-arrow-left class="h-4 w-4" />
                            {{ __('Torna indietro') }}
                        </a>
                    @elseif ($mode === 'verification_sent')
                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
                            <p>{{ __('Ti abbiamo inviato una mail a :email', ['email' => $maskedEmail]) }}</p>
                            <p class="mt-2 text-base-content/65">{{ __('Non l\'hai ricevuta? Controlla nello spam.') }}</p>
                        </div>

                        <div class="flex flex-col gap-3">
                            <form method="POST" action="{{ route('onboarding.email.resend') }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-outline w-full"
                                    @disabled($resendAvailableIn > 0)
                                    data-resend-button
                                    data-initial-seconds="{{ $resendAvailableIn }}"
                                >
                                    {{ __('Invia di nuovo il codice') }}
                                </button>
                            </form>

                            <a href="{{ route('onboarding.email.show', ['edit' => 1]) }}" class="btn btn-ghost">
                                {{ __('Modifica l\'email') }}
                            </a>
                        </div>
                    @else
                        <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
                            <p>{{ __('Il tuo account è già attivo.') }}</p>
                            <p class="mt-2">{{ __('Se vuoi, possiamo inviarti una mail per il recupero password a :email.', ['email' => $maskedEmail]) }}</p>
                        </div>

                        <div class="flex flex-col gap-3">
                            <form method="POST" action="{{ route('onboarding.password-reset') }}">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full">
                                    {{ __('Invia recupero password') }}
                                </button>
                            </form>

                            <a href="{{ route('login') }}" class="btn btn-ghost">
                                {{ __('Torna al login') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const resendButton = document.querySelector('[data-resend-button]');

            if (! resendButton) {
                return;
            }

            let remainingSeconds = Number(resendButton.dataset.initialSeconds || 0);
            const originalLabel = resendButton.textContent.trim();

            const renderState = () => {
                if (remainingSeconds <= 0) {
                    resendButton.disabled = false;
                    resendButton.textContent = originalLabel;

                    return;
                }

                resendButton.disabled = true;
                resendButton.textContent = `${originalLabel} (${remainingSeconds}s)`;
                remainingSeconds -= 1;
                window.setTimeout(renderState, 1000);
            };

            renderState();
        });
    </script>
</x-layouts.app>
