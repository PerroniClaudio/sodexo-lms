@props([
    'countryValue' => null,
    'regionValue' => null, 
    'provinceValue' => null,
    'cityValue' => null,
    'addressValue' => null,
    'postalCodeValue' => null,
    'required' => false,
])

<div class="space-y-4" data-address-selector data-country="{{ $countryValue }}" data-region="{{ $regionValue }}" data-province="{{ $provinceValue }}" data-city="{{ $cityValue }}" data-address="{{ $addressValue }}" data-postal-code="{{ $postalCodeValue }}">
    <!-- Paese -->
    <div class="form-control">
        <label class="label">
            <span class="label-text">
                {{ __('Paese') }}
                @if($required)<span class="text-error">*</span>@endif
            </span>
        </label>
        <div class="relative">
            <select 
                name="country" 
                class="select select-bordered w-full"
                {{ $required ? 'required' : '' }}
            >
                <option value="">{{ __('Seleziona un paese...') }}</option>
            </select>
            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                <span class="loading loading-spinner loading-sm" style="display:none;" loading-countries></span>
            </div>
        </div>
        <div class="mt-2">
            <input 
                type="search" 
                class="input input-bordered input-sm w-full" 
                placeholder="{{ __('Cerca paese...') }}"
            >
        </div>
    </div>

    <!-- Regione -->
    <div class="form-control" data-region-block>
        <label class="label">
            <span class="label-text">{{ __('Regione') }}</span>
        </label>
        <div class="relative">
            <select 
                name="region" 
                class="select select-bordered w-full"
            >
                <option value="">{{ __('Seleziona una regione...') }}</option>
            </select>
            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                <span class="loading loading-spinner loading-sm" style="display:none;" loading-regions></span>
            </div>
        </div>
        <div class="mt-2">
            <input 
                type="search" 
                class="input input-bordered input-sm w-full" 
                placeholder="{{ __('Cerca regione...') }}"
            >
        </div>
    </div>

    <!-- Provincia (solo per Italia) -->
    <div class="form-control" data-province-block>
        <label class="label">
            <span class="label-text">{{ __('Provincia') }}</span>
        </label>
        <div class="relative">
            <select 
                name="province" 
                class="select select-bordered w-full"
            >
                <option value="">{{ __('Seleziona una provincia...') }}</option>
            </select>
            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                <span class="loading loading-spinner loading-sm" style="display:none;" loading-provinces></span>
            </div>
        </div>
        <div class="mt-2">
            <input 
                type="search" 
                class="input input-bordered input-sm w-full" 
                placeholder="{{ __('Cerca provincia...') }}"
            >
        </div>
    </div>

    <!-- Città -->
    <div class="form-control" data-city-block>
        <label class="label">
            <span class="label-text">{{ __('Città') }}</span>
        </label>
        <div class="relative">
            <select 
                name="city" 
                class="select select-bordered w-full"
            >
                <option value="">{{ __('Seleziona una città...') }}</option>
            </select>
            <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                <span class="loading loading-spinner loading-sm" style="display:none;" loading-cities></span>
            </div>
        </div>
        <div class="mt-2">
            <input 
                type="search" 
                class="input input-bordered input-sm w-full" 
                placeholder="{{ __('Cerca città...') }}"
            >
        </div>
    </div>

    <!-- Indirizzo -->
    <div class="form-control">
        <label class="label">
            <span class="label-text">{{ __('Indirizzo') }}</span>
        </label>
        <input 
            type="text" 
            name="address" 
            class="input input-bordered w-full" 
            placeholder="{{ __('Via, numero civico...') }}"
        >
    </div>

    <!-- Codice Postale -->
    <div class="form-control">
        <label class="label">
            <span class="label-text">{{ __('Codice Postale') }}</span>
        </label>
        <input 
            type="text" 
            name="postal_code" 
            class="input input-bordered w-full" 
            placeholder="{{ __('CAP / Codice Postale') }}"
        >
    </div>
</div>

<script>
function addressSelector(initialValues = {}) {
    return {
        // Valori selezionati
        selectedCountry: initialValues.country || '',
        selectedRegion: initialValues.region || '',
        selectedProvince: initialValues.province || '',
        selectedCity: initialValues.city || '',
        selectedAddress: initialValues.address || '',
        selectedPostalCode: initialValues.postalCode || '',
        
        // Opzioni disponibili
        countries: [],
        regions: [],
        provinces: [],
        cities: [],
        
        // Ricerca
        searchCountry: '',
        searchRegion: '',
        searchProvince: '',
        searchCity: '',
        
        // Loading states
        loadingCountries: false,
        loadingRegions: false,
        loadingProvinces: false,
        loadingCities: false,
        
        // Timeout per debounce
        searchTimeout: null,

        init() {
            this.loadCountries();
            
            // Se ci sono valori iniziali, carica i dati correlati
            if (this.selectedCountry) {
                <div class="form-control">
                    <label class="label">
                        <span class="label-text">{{ __('Codice Postale') }}</span>
                    </label>
                    <input 
                        type="text" 
                        name="postal_code" 
                        class="input input-bordered w-full" 
                        placeholder="{{ __('CAP / Codice Postale') }}"
                    >
                </div>
            </div>
            @once
                @push('scripts')
                    <script src="{{ asset('build/address-selection.js') }}"></script>
                @endpush
            @endonce
            this.searchTimeout = setTimeout(() => {
                this.loadCities();
            }, 300);
        }
    };
}
</script>