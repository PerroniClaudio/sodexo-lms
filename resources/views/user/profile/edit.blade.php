<x-layouts.user>
    {{-- <div class="flex min-h-screen items-center justify-center p-4"> --}}
    <div class="mx-auto max-w-5xl p-4 sm:p-6 lg:p-8">
        <div class="w-full">
            <div class="card border border-base-300 bg-base-100 shadow-xl">
                <div class="card-body gap-6">
                    <div class="text-center">
                        <div class="flex justify-center mb-4">
                            <div class="rounded-full bg-primary/10 p-3">
                                <x-lucide-user-round class="h-8 w-8 text-primary" />
                            </div>
                        </div>
                        <h1 class="card-title justify-center text-2xl">
                            {{ __('Il mio profilo') }}
                            @php
                                $accountType = $user->getRoleNames()->first() ?? ($user->account_type ?? null);
                            @endphp
                            @if($accountType)
                                <span class="text-base font-normal text-base-content/60">({{ ucfirst($accountType) }})</span>
                            @endif
                        </h1>
                        <p class="text-sm text-base-content/70 mt-2">
                            {{ __('Visualizza e aggiorna i tuoi dati personali.') }}
                        </p>
                        <p class="text-sm text-base-content/70 mt-2">
                            {{ __('Per correggere dati non modificabili, contatta l\'amministratore.') }}
                        </p>
                    </div>

                    <form method="POST" action="{{ route('user.profile.update') }}" class="flex flex-col gap-6">
                        @csrf
                        @method('PUT')

                        <!-- SEZIONE UTENTE -->
                        <div class="mt-4 mb-2">
                            <span class="font-bold text-primary text-lg">Utente</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                            <div class="form-control flex flex-col w-full">
                                <label class="label"><span class="label-text font-semibold">Nome</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->name }}" readonly>
                            </div>
                            <div class="form-control flex flex-col w-full">
                                <label class="label"><span class="label-text font-semibold">Cognome</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->surname }}" readonly>
                            </div>
                            <div class="form-control flex flex-col w-full">
                                <label class="label"><span class="label-text font-semibold">Codice Fiscale</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->fiscal_code }}" readonly>
                            </div>
                            <div class="form-control flex flex-col w-full">
                                <label class="label"><span class="label-text font-semibold">Straniero/Immigrato</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->is_foreigner_or_immigrant ? 'Sì' : 'No' }}" readonly>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                            <div class="form-control flex flex-col w-full">
                                <label class="label"><span class="label-text font-semibold">Data di nascita</span></label>
                                <input type="date" name="birth_date" class="input input-bordered w-full @error('birth_date') input-error @enderror" value="{{ old('birth_date', $user->birth_date ? $user->birth_date->format('Y-m-d') : '') }}">
                                @error('birth_date')<span class="text-error text-sm">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-control flex flex-col w-full">
                                <label class="label"><span class="label-text font-semibold">Luogo di nascita</span></label>
                                <input type="text" name="birth_place" class="input input-bordered w-full @error('birth_place') input-error @enderror" value="{{ old('birth_place', $user->birth_place) }}">
                                @error('birth_place')<span class="text-error text-sm">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-control flex flex-col w-full">
                                <label class="label"><span class="label-text font-semibold">Genere</span></label>
                                <select name="gender" class="select select-bordered w-full @error('gender') input-error @enderror">
                                    <option value="">Non specificato</option>
                                    <option value="M" @selected(old('gender', $user->gender ?? '') == 'M')>Maschio</option>
                                    <option value="F" @selected(old('gender', $user->gender ?? '') == 'F')>Femmina</option>
                                </select>
                                @error('gender')<span class="text-error text-sm">{{ $message }}</span>@enderror
                            </div>
                            <div class="form-control flex flex-col w-full">
                                <label class="label"><span class="label-text font-semibold">Telefono</span></label>
                                <div class="flex gap-2">
                                    <input type="text" name="phone_prefix" class="input input-bordered w-24 @error('phone_prefix') input-error @enderror" placeholder="+39" value="{{ old('phone_prefix', $user->phone_prefix ?? '+39') }}">
                                    <input type="text" name="phone" class="input input-bordered flex-1 @error('phone') input-error @enderror" placeholder="Numero di telefono" value="{{ old('phone', $user->phone) }}">
                                </div>
                                @error('phone_prefix')<span class="text-error text-sm">{{ $message }}</span>@enderror
                                @error('phone')<span class="text-error text-sm">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- SEZIONE RESIDENZA/DOMICILIO -->
                        <div class="mt-4 mb-2">
                            <span class="font-bold text-primary text-lg">Residenza/Domicilio</span>
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

                        <!-- SEZIONE LAVORO (SOLO LETTURA) -->
                        <div class="mt-4 mb-2">
                            <span class="font-bold text-primary text-lg">Dati lavorativi</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 w-full">
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Settore</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobSector?->name }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Categoria</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobCategory?->name }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Livello di inquadramento</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobLevel?->name }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Mansione</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobTitle?->name }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Ruolo</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobRole?->name }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Unità Produttiva</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobUnit?->name }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Paese Unità Produttiva</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobUnit?->country?->name ?? '' }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Regione Unità Produttiva</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobUnit?->region?->name ?? '' }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Provincia Unità Produttiva</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobUnit?->province?->name ?? '' }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Città Unità Produttiva</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobUnit?->city?->name ?? '' }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">CAP Unità Produttiva</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobUnit?->postal_code ?? '' }}" readonly>
                            </div>
                            <div class="form-control flex flex-col">
                                <label class="label"><span class="label-text font-semibold">Indirizzo Unità Produttiva</span></label>
                                <input type="text" class="input input-bordered w-full" value="{{ $user->jobUnit?->address }}" readonly>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6">
                            <button type="submit" class="btn btn-primary">Salva modifiche</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
                                
