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
                            {{ $requiresProfileDetails
                                ? __('Imposta la tua password e completa gli ultimi dati richiesti per attivare il tuo account')
                                : __('Imposta la tua password per completare l\'attivazione') }}
                        </p>
                    </div>

                    <div class="alert alert-info">
                        <x-lucide-info class="h-5 w-5 shrink-0" />
                        <div class="text-sm">
                            <p><strong>{{ __('Email:') }}</strong> {{ $user->email }}</p>
                            <p class="mt-1">
                                {{ $requiresProfileDetails
                                    ? __('Completa gli ultimi dati richiesti per attivare il tuo account.')
                                    : __('Ti basta impostare la password per attivare il tuo account.') }}
                            </p>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-error">
                            <x-lucide-circle-alert class="h-5 w-5 shrink-0" />
                            <div class="text-sm">
                                <p class="font-medium">{{ __('Controlla i dati inseriti.') }}</p>
                                <ul class="mt-2 list-disc pl-4">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

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
                                class="input input-bordered w-full @error('password') input-error @enderror"
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
                                class="input input-bordered w-full @error('password_confirmation') input-error @enderror"
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

                        @if ($requiresProfileDetails)
                            <div class="form-control">
                                <label for="birth_date" class="label">
                                    <span class="label-text font-medium">{{ __('Data di nascita') }}</span>
                                </label>
                                <input
                                    id="birth_date"
                                    name="birth_date"
                                    type="date"
                                    value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"
                                    class="input input-bordered w-full @error('birth_date') input-error @enderror"
                                    required
                                >
                            </div>

                            <div class="form-control">
                                <label for="birth_place" class="label">
                                    <span class="label-text font-medium">{{ __('Luogo di nascita') }}</span>
                                </label>
                                <input
                                    id="birth_place"
                                    name="birth_place"
                                    type="text"
                                    value="{{ old('birth_place', $user->birth_place) }}"
                                    class="input input-bordered w-full @error('birth_place') input-error @enderror"
                                    required
                                >
                            </div>

                            <div class="form-control">
                                <label for="citizenship_country_id" class="label">
                                    <span class="label-text font-medium">{{ __('Paese di cittadinanza') }}</span>
                                </label>
                                <select id="citizenship_country_id" name="citizenship_country_id" class="select select-bordered w-full @error('citizenship_country_id') select-error @enderror">
                                    <option value="">{{ __('Seleziona un paese') }}</option>
                                    @foreach ($availableCountries as $country)
                                        <option value="{{ $country->id }}" @selected((string) old('citizenship_country_id', $user->citizenship_country_id) === (string) $country->id)>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary mt-2">
                            {{ __('Attiva Account') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
