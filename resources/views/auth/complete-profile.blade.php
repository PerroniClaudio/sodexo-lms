<x-layouts.app>
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-2xl">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <div class="flex justify-center mb-4">
                            <div class="rounded-full bg-primary/10 p-3">
                                <x-lucide-user-round class="h-8 w-8 text-primary" />
                            </div>
                        </div>
                        <h1 class="card-title justify-center text-2xl">
                            {{ __('Completa il tuo profilo') }}
                        </h1>
                        <p class="text-sm text-base-content/70 mt-2">
                            {{ __('Inserisci i tuoi dati personali. Tutti i campi sono facoltativi.') }}
                        </p>
                    </div>

                    <div class="alert alert-info">
                        <x-lucide-info class="h-5 w-5 shrink-0" />
                        <div class="text-sm">
                            <p><strong>{{ __('Benvenuto,') }} {{ $user->name }}!</strong></p>
                            <p class="mt-1">{{ __('I tuoi dati anagrafici principali sono già stati inseriti. Puoi completare o aggiornare le informazioni qui sotto.') }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('onboarding.store') }}" class="flex flex-col gap-6">
                        @csrf

                        <div class="divider text-sm">{{ __('Dati di Nascita') }}</div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="form-control">
                                <label for="birth_date" class="label">
                                    <span class="label-text">{{ __('Data di Nascita') }}</span>
                                </label>
                                <input
                                    id="birth_date"
                                    name="birth_date"
                                    type="date"
                                    value="{{ old('birth_date', $user->birth_date?->format('Y-m-d')) }}"
                                    class="input input-bordered @error('birth_date') input-error @enderror"
                                    max="{{ now()->format('Y-m-d') }}"
                                >
                                @error('birth_date')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label for="birth_place" class="label">
                                    <span class="label-text">{{ __('Luogo di Nascita') }}</span>
                                </label>
                                <input
                                    id="birth_place"
                                    name="birth_place"
                                    type="text"
                                    value="{{ old('birth_place', $user->birth_place) }}"
                                    class="input input-bordered @error('birth_place') input-error @enderror"
                                >
                                @error('birth_place')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>

                        <div class="form-control">
                            <label for="gender" class="label">
                                <span class="label-text">{{ __('Genere') }}</span>
                            </label>
                            <select
                                id="gender"
                                name="gender"
                                class="select select-bordered @error('gender') select-error @enderror"
                            >
                                <option value="">{{ __('Seleziona') }}</option>
                                <option value="M" @selected(old('gender', $user->gender) === 'M')>{{ __('Maschile') }}</option>
                                <option value="F" @selected(old('gender', $user->gender) === 'F')>{{ __('Femminile') }}</option>
                            </select>
                            @error('gender')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="divider text-sm">{{ __('Contatti') }}</div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="form-control">
                                <label for="phone_prefix" class="label">
                                    <span class="label-text">{{ __('Prefisso') }}</span>
                                </label>
                                <input
                                    id="phone_prefix"
                                    name="phone_prefix"
                                    type="text"
                                    value="{{ old('phone_prefix', $user->phone_prefix ?? '+39') }}"
                                    class="input input-bordered @error('phone_prefix') input-error @enderror"
                                    placeholder="+39"
                                >
                                @error('phone_prefix')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>

                            <div class="form-control md:col-span-2">
                                <label for="phone" class="label">
                                    <span class="label-text">{{ __('Telefono') }}</span>
                                </label>
                                <input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    value="{{ old('phone', $user->phone) }}"
                                    class="input input-bordered @error('phone') input-error @enderror"
                                    placeholder="3201234567"
                                >
                                @error('phone')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>

                        <div class="divider text-sm">{{ __('Indirizzo di Residenza') }}</div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="form-control">
                                <label for="nation" class="label">
                                    <span class="label-text">{{ __('Nazione') }}</span>
                                </label>
                                <input
                                    id="nation"
                                    name="nation"
                                    type="text"
                                    value="{{ old('nation', $user->nation ?? 'IT') }}"
                                    class="input input-bordered @error('nation') input-error @enderror"
                                    placeholder="IT"
                                    maxlength="2"
                                >
                                @error('nation')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label for="region" class="label">
                                    <span class="label-text">{{ __('Regione') }}</span>
                                </label>
                                <input
                                    id="region"
                                    name="region"
                                    type="text"
                                    value="{{ old('region', $user->region) }}"
                                    class="input input-bordered @error('region') input-error @enderror"
                                >
                                @error('region')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label for="province" class="label">
                                    <span class="label-text">{{ __('Provincia') }}</span>
                                </label>
                                <input
                                    id="province"
                                    name="province"
                                    type="text"
                                    value="{{ old('province', $user->province) }}"
                                    class="input input-bordered @error('province') input-error @enderror"
                                    placeholder="MI"
                                    maxlength="2"
                                >
                                @error('province')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="form-control">
                                <label for="city" class="label">
                                    <span class="label-text">{{ __('Città') }}</span>
                                </label>
                                <input
                                    id="city"
                                    name="city"
                                    type="text"
                                    value="{{ old('city', $user->city) }}"
                                    class="input input-bordered @error('city') input-error @enderror"
                                >
                                @error('city')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>

                            <div class="form-control">
                                <label for="postal_code" class="label">
                                    <span class="label-text">{{ __('CAP') }}</span>
                                </label>
                                <input
                                    id="postal_code"
                                    name="postal_code"
                                    type="text"
                                    value="{{ old('postal_code', $user->postal_code) }}"
                                    class="input input-bordered @error('postal_code') input-error @enderror"
                                    placeholder="20100"
                                    maxlength="10"
                                >
                                @error('postal_code')
                                    <label class="label">
                                        <span class="label-text-alt text-error">{{ $message }}</span>
                                    </label>
                                @enderror
                            </div>
                        </div>

                        <div class="form-control md:col-span-2">
                            <label for="address" class="label">
                                <span class="label-text">{{ __('Indirizzo') }}</span>
                            </label>
                            <input
                                id="address"
                                name="address"
                                type="text"
                                value="{{ old('address', $user->address) }}"
                                class="input input-bordered @error('address') input-error @enderror"
                                placeholder="Via Roma 123"
                            >
                            @error('address')
                                <label class="label">
                                    <span class="label-text-alt text-error">{{ $message }}</span>
                                </label>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button type="submit" class="btn btn-primary">
                                {{ __('Completa Profilo') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
