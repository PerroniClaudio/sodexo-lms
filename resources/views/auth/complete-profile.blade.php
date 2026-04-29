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


                                                        @if ($errors->any())
                                                            <div class="alert alert-error mb-4">
                                                                <ul class="list-disc pl-5">
                                                                    @foreach ($errors->all() as $error)
                                                                        <li>{{ $error }}</li>
                                                                    @endforeach
                                                                </ul>
                                                            </div>
                                                        @endif

                                                        <form method="POST" action="{{ route('onboarding.store') }}" class="flex flex-col gap-6">
                                                            @csrf

                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                <div class="form-control">
                                                                    <label for="name" class="label">
                                                                        <span class="label-text font-semibold">{{ __('Nome') }}</span>
                                                                    </label>
                                                                    <input type="text" name="name" id="name" value="{{ old('name', $user->name ?? '') }}" class="input input-bordered w-full" required>
                                                                    @error('name')<span class="text-error text-sm">{{ $message }}</span>@enderror
                                                                </div>
                                                                <div class="form-control">
                                                                    <label for="surname" class="label">
                                                                        <span class="label-text font-semibold">{{ __('Cognome') }}</span>
                                                                    </label>
                                                                    <input type="text" name="surname" id="surname" value="{{ old('surname', $user->surname ?? '') }}" class="input input-bordered w-full" required>
                                                                    @error('surname')<span class="text-error text-sm">{{ $message }}</span>@enderror
                                                                </div>
                                                            </div>

                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                <div class="form-control">
                                                                    <label for="birth_date" class="label">
                                                                        <span class="label-text font-semibold">{{ __('Data di nascita') }}</span>
                                                                    </label>
                                                                    <input type="date" name="birth_date" id="birth_date" class="input input-bordered w-full" value="{{ old('birth_date', isset($user) && $user->birth_date ? $user->birth_date->format('Y-m-d') : '') }}">
                                                                    @error('birth_date')<span class="text-error text-sm">{{ $message }}</span>@enderror
                                                                </div>
                                                                <div class="form-control">
                                                                    <label for="birth_place" class="label">
                                                                        <span class="label-text font-semibold">{{ __('Luogo di nascita') }}</span>
                                                                    </label>
                                                                    <input type="text" name="birth_place" id="birth_place" class="input input-bordered w-full" value="{{ old('birth_place', $user->birth_place ?? '') }}">
                                                                    @error('birth_place')<span class="text-error text-sm">{{ $message }}</span>@enderror
                                                                </div>
                                                            </div>

                                                            <div class="form-control">
                                                                <label for="gender" class="label">
                                                                    <span class="label-text font-semibold">{{ __('Genere') }}</span>
                                                                </label>
                                                                <select name="gender" id="gender" class="select select-bordered w-full">
                                                                    <option value="">{{ __('profile.options.unspecified') }}</option>
                                                                    <option value="M" @selected(old('gender', $user->gender ?? '') == 'M')>{{ __('Maschio') }}</option>
                                                                    <option value="F" @selected(old('gender', $user->gender ?? '') == 'F')>{{ __('Femmina') }}</option>
                                                                </select>
                                                                @error('gender')<span class="text-error text-sm">{{ $message }}</span>@enderror
                                                            </div>

                                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                                <div class="form-control">
                                                                    <label for="phone_prefix" class="label block">
                                                                        <span class="label-text font-semibold">{{ __('Prefisso nazionale') }}</span>
                                                                    </label>
                                                                    <input type="text" name="phone_prefix" id="phone_prefix" class="input input-bordered w-fit flex-0" placeholder="+39" value="{{ old('phone_prefix', $user->phone_prefix ?? '+39') }}">
                                                                    @error('phone_prefix')<span class="text-error text-sm">{{ $message }}</span>@enderror
                                                                </div>
                                                                <div class="form-control">
                                                                    <label for="phone" class="label">
                                                                        <span class="label-text font-semibold">{{ __('Telefono') }}</span>
                                                                    </label>
                                                                    <input type="text" name="phone" id="phone" class="input input-bordered flex-1" placeholder="{{ __('forms.phone_number_placeholder') }}" value="{{ old('phone', $user->phone ?? '') }}">
                                                                    @error('phone')<span class="text-error text-sm">{{ $message }}</span>@enderror
                                                                </div>
                                                            </div>

                                                            <div class="mt-4 mb-2">
                                                                <span class="font-bold text-primary text-lg">{{ __('profile.sections.residence') }}</span>
                                                            </div>
                                                            <x-address-selector-simple 
                                                                :countryValue="old('country', $user->homeCountry?->code ?? 'it')"
                                                                :regionValue="old('region', $user->homeRegion?->name ?? '')"
                                                                :provinceValue="old('province', $user->homeProvince?->name ?? '')"
                                                                :cityValue="old('city', $user->homeCity?->name ?? '')"
                                                                :addressValue="old('address', $user->address ?? '')"
                                                                :postalCodeValue="old('postal_code', $user->postal_code ?? '')"
                                                                :required="false"
                                                            />

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
