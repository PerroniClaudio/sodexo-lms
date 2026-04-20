@props([
    'countryValue' => null,
    'regionValue' => null, 
    'provinceValue' => null,
    'cityValue' => null,
    'addressValue' => null,
    'postalCodeValue' => null,
    'required' => false,
])

<div class="space-y-4" x-data="addressSelector({
    country: '{{ $countryValue }}',
    region: '{{ $regionValue }}',
    province: '{{ $provinceValue }}', 
    city: '{{ $cityValue }}',
    address: '{{ $addressValue }}',
    postalCode: '{{ $postalCodeValue }}'
})">
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
                x-model="selectedCountry"
                @change="countryChanged()"
                {{ $required ? 'required' : '' }}
            >
                <option value="">{{ __('Seleziona un paese...') }}</option>
                <template x-for="country in countries" :key="country.value">
                    <option :value="country.value" x-text="country.label"></option>
                </template>
            </select>
            <div x-show="loadingCountries" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                <span class="loading loading-spinner loading-sm"></span>
            </div>
        </div>
        <div class="mt-2">
            <input 
                type="search" 
                x-model="searchCountry"
                @input="searchCountries()"
                class="input input-bordered input-sm w-full" 
                placeholder="{{ __('Cerca paese...') }}"
            >
        </div>
    </div>

    <!-- Regione -->
    <div class="form-control" x-show="selectedCountry">
        <label class="label">
            <span class="label-text">{{ __('Regione') }}</span>
        </label>
        <div class="relative">
            <select 
                name="region" 
                class="select select-bordered w-full"
                x-model="selectedRegion" 
                @change="regionChanged()"
                :disabled="!selectedCountry"
            >
                <option value="">{{ __('Seleziona una regione...') }}</option>
                <template x-for="region in regions" :key="region.value">
                    <option :value="region.value" x-text="region.label"></option>
                </template>
            </select>
            <div x-show="loadingRegions" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                <span class="loading loading-spinner loading-sm"></span>
            </div>
        </div>
        <div class="mt-2">
            <input 
                type="search" 
                x-model="searchRegion"
                @input="searchRegions()"
                class="input input-bordered input-sm w-full" 
                placeholder="{{ __('Cerca regione...') }}"
            >
        </div>
    </div>

    <!-- Provincia (solo per Italia) -->
    <div class="form-control" x-show="selectedCountry === 'IT' && selectedRegion">
        <label class="label">
            <span class="label-text">{{ __('Provincia') }}</span>
        </label>
        <div class="relative">
            <select 
                name="province" 
                class="select select-bordered w-full"
                x-model="selectedProvince"
                @change="provinceChanged()"
                :disabled="!selectedRegion"
            >
                <option value="">{{ __('Seleziona una provincia...') }}</option>
                <template x-for="province in provinces" :key="province.value">
                    <option :value="province.value" x-text="province.label"></option>
                </template>
            </select>
            <div x-show="loadingProvinces" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                <span class="loading loading-spinner loading-sm"></span>
            </div>
        </div>
        <div class="mt-2">
            <input 
                type="search" 
                x-model="searchProvince"
                @input="searchProvinces()"
                class="input input-bordered input-sm w-full" 
                placeholder="{{ __('Cerca provincia...') }}"
            >
        </div>
    </div>

    <!-- Città -->
    <div class="form-control" x-show="selectedRegion">
        <label class="label">
            <span class="label-text">{{ __('Città') }}</span>
        </label>
        <div class="relative">
            <select 
                name="city" 
                class="select select-bordered w-full"
                x-model="selectedCity"
                :disabled="!selectedRegion"
            >
                <option value="">{{ __('Seleziona una città...') }}</option>
                <template x-for="city in cities" :key="city.value">
                    <option :value="city.value" x-text="city.label"></option>
                </template>
            </select>
            <div x-show="loadingCities" class="absolute right-3 top-1/2 transform -translate-y-1/2">
                <span class="loading loading-spinner loading-sm"></span>
            </div>
        </div>
        <div class="mt-2">
            <input 
                type="search" 
                x-model="searchCity"
                @input="searchCities()"
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
            x-model="selectedAddress"
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
            x-model="selectedPostalCode"
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
                this.loadRegions().then(() => {
                    if (this.selectedRegion) {
                        if (this.selectedCountry === 'IT') {
                            this.loadProvinces();
                        }
                        this.loadCities();
                    }
                });
            }
        },

        async loadCountries() {
            this.loadingCountries = true;
            try {
                const response = await fetch(`/api/geographic/countries?search=${encodeURIComponent(this.searchCountry)}&locale=it`);
                const data = await response.json();
                this.countries = data.map(country => ({
                    value: country.code,
                    label: country.name
                }));
            } catch (error) {
                console.error('Error loading countries:', error);
            } finally {
                this.loadingCountries = false;
            }
        },

        async loadRegions() {
            if (!this.selectedCountry) {
                this.regions = [];
                return;
            }
            
            this.loadingRegions = true;
            try {
                const response = await fetch(`/api/geographic/regions/${encodeURIComponent(this.selectedCountry)}?search=${encodeURIComponent(this.searchRegion)}&locale=it`);
                const data = await response.json();
                this.regions = data.map(region => ({
                    value: region.id,
                    label: region.name,
                    division_id: region.id
                }));
            } catch (error) {
                console.error('Error loading regions:', error);
            } finally {
                this.loadingRegions = false;
            }
        },

        async loadProvinces() {
            if (!this.selectedRegion || this.selectedCountry !== 'IT') {
                this.provinces = [];
                return;
            }
            
            this.loadingProvinces = true;
            try {
                const response = await fetch(`/api/geographic/provinces/${encodeURIComponent(this.selectedRegion)}?search=${encodeURIComponent(this.searchProvince)}&locale=it`);
                const data = await response.json();
                this.provinces = data.map(province => ({
                    value: province.id,
                    label: province.name
                }));
            } catch (error) {
                console.error('Error loading provinces:', error);
            } finally {
                this.loadingProvinces = false;
            }
        },

        async loadCities() {
            if (!this.selectedRegion) {
                this.cities = [];
                return;
            }
            
            // Per l'Italia, usa la provincia se disponibile, altrimenti la regione
            const divisionId = this.selectedProvince || this.selectedRegion;
            
            this.loadingCities = true;
            try {
                const response = await fetch(`/api/geographic/cities/${encodeURIComponent(divisionId)}?search=${encodeURIComponent(this.searchCity)}&locale=it`);
                const data = await response.json();
                this.cities = data.map(city => ({
                    value: city.id,
                    label: city.name
                }));
            } catch (error) {
                console.error('Error loading cities:', error);
            } finally {
                this.loadingCities = false;
            }
        },

        countryChanged() {
            this.selectedRegion = '';
            this.selectedProvince = '';
            this.selectedCity = '';
            this.regions = [];
            this.provinces = [];
            this.cities = [];
            
            if (this.selectedCountry) {
                this.loadRegions();
            }
        },

        regionChanged() {
            this.selectedProvince = '';
            this.selectedCity = '';
            this.provinces = [];
            this.cities = [];
            
            if (this.selectedRegion) {
                if (this.selectedCountry === 'IT') {
                    this.loadProvinces();
                }
                this.loadCities();
            }
        },

        provinceChanged() {
            this.selectedCity = '';
            this.cities = [];
        },

        searchCountries() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadCountries();
            }, 300);
        },

        searchRegions() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadRegions();
            }, 300);
        },

        searchProvinces() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadProvinces();
            }, 300);
        },

        searchCities() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadCities();
            }, 300);
        }
    };
}
</script>