@props([
    'countryValue' => 'it',
    'regionValue' => null,
    'provinceValue' => null,
    'cityValue' => null,
    'addressValue' => null,
    'postalCodeValue' => null,
    'required' => false,
])

@php
    $uniqueId = 'address-selector-' . uniqid();
@endphp

<div class="space-y-4" id="{{ $uniqueId }}">
    <!-- Paese -->
    <div class="form-control">
        <label class="label">
            <span class="label-text font-semibold">
                {{ __('Paese') }}
                @if($required)<span class="text-error">*</span>@endif
            </span>
        </label>
        <select 
            name="country" 
            class="select select-bordered w-full country-select"
            {{ $required ? 'required' : '' }}
        >
            <option value="">{{ __('Seleziona un paese...') }}</option>
        </select>
        <span class="loading-text text-sm text-base-content/70 hidden">{{ __('Caricamento...') }}</span>
    </div>

    <!-- Regione -->
    <div class="form-control region-container hidden">
        <label class="label">
            <span class="label-text font-semibold">
                {{ __('Regione') }}
                @if($required)<span class="text-error">*</span>@endif
            </span>
        </label>
        <select 
            name="region" 
            class="select select-bordered w-full region-select"
            {{ $required ? 'required' : '' }}
        >
            <option value="">{{ __('Seleziona una regione...') }}</option>
        </select>
        <span class="loading-text text-sm text-base-content/70 hidden">{{ __('Caricamento...') }}</span>
    </div>

    <!-- Provincia (solo per Italia) -->
    <div class="form-control province-container hidden">
        <label class="label">
            <span class="label-text font-semibold">{{ __('Provincia') }}</span>
        </label>
        <select 
            name="province" 
            class="select select-bordered w-full province-select"
        >
            <option value="">{{ __('Seleziona una provincia...') }}</option>
        </select>
        <span class="loading-text text-sm text-base-content/70 hidden">{{ __('Caricamento...') }}</span>
    </div>

    <!-- Città -->
    <div class="form-control city-container hidden">
        <label class="label">
            <span class="label-text font-semibold">
                {{ __('Città') }}
                @if($required)<span class="text-error">*</span>@endif
            </span>
        </label>
        <select 
            name="city" 
            class="select select-bordered w-full city-select"
            {{ $required ? 'required' : '' }}
        >
            <option value="">{{ __('Seleziona una città...') }}</option>
        </select>
        <span class="loading-text text-sm text-base-content/70 hidden">{{ __('Caricamento...') }}</span>
    </div>

    <!-- Indirizzo -->
    <div class="form-control">
        <label class="label">
            <span class="label-text font-semibold">{{ __('Indirizzo') }}</span>
        </label>
        <input 
            type="text" 
            name="address" 
            value="{{ old('address', $addressValue) }}"
            class="input input-bordered w-full" 
            placeholder="{{ __('Via, numero civico...') }}"
        >
    </div>

    <!-- Codice Postale -->
    <div class="form-control">
        <label class="label">
            <span class="label-text font-semibold">{{ __('Codice Postale') }}</span>
        </label>
        <div class="relative">
            <input 
                type="text" 
                name="postal_code" 
                value="{{ old('postal_code', $postalCodeValue) }}"
                class="input input-bordered w-full postal-code-input" 
                placeholder="{{ __('CAP / Codice Postale') }}"
                maxlength="10"
            >
            <span class="absolute inset-y-0 right-3 items-center pointer-events-none hidden postal-code-loading">
                <span class="loading loading-spinner loading-sm"></span>
            </span>
        </div>
        <label class="label">
            <span class="label-text-alt text-base-content/70">
                {{ __('Per l\'Italia, inserisci il CAP per compilare automaticamente i campi sopra') }}
            </span>
        </label>
    </div>
</div>

<script>
(function() {
    const containerId = '{{ $uniqueId }}';
    const container = document.getElementById(containerId);
    
    // Elementi DOM
    const countrySelect = container.querySelector('.country-select');
    const regionSelect = container.querySelector('.region-select');
    const provinceSelect = container.querySelector('.province-select');
    const citySelect = container.querySelector('.city-select');
    const postalCodeInput = container.querySelector('.postal-code-input');
    const postalCodeLoading = container.querySelector('.postal-code-loading');
    
    const regionContainer = container.querySelector('.region-container');
    const provinceContainer = container.querySelector('.province-container');
    const cityContainer = container.querySelector('.city-container');
    
    // Valori iniziali
    const initialCountry = '{{ $countryValue ?: "it" }}';
    const initialRegion = '{{ $regionValue ?: "" }}';
    const initialProvince = '{{ $provinceValue ?: "" }}';
    const initialCity = '{{ $cityValue ?: "" }}';
    
    // Funzioni helper
    function showLoading(selectElement, show) {
        const loadingText = selectElement.parentElement.querySelector('.loading-text');
        if (loadingText) {
            loadingText.classList.toggle('hidden', !show);
        }
        selectElement.disabled = show;
    }
    
    function clearSelect(selectElement) {
        selectElement.innerHTML = '<option value="">Seleziona...</option>';
    }
    
    function populateSelect(selectElement, items, valueProp, labelProp, selectedValue = '', includeIdAttr = false) {
        clearSelect(selectElement);
        items.forEach(item => {
            const option = document.createElement('option');
            const itemValue = String(item[valueProp] || '');
            option.value = itemValue;
            option.textContent = item[labelProp];
            // Aggiungi sempre data-id per poterlo recuperare quando serve
            if (item.id) {
                option.setAttribute('data-id', item.id);
            }
            // Aggiungi province_id se presente (per le città)
            if (item.province_id) {
                option.setAttribute('data-province-id', item.province_id);
            }
            // Confronto più robusto
            if (selectedValue && itemValue.toLowerCase() === String(selectedValue).toLowerCase()) {
                option.selected = true;
            }
            selectElement.appendChild(option);
        });
    }
    
    // Carica paesi
    async function loadCountries() {
        showLoading(countrySelect, true);
        
        try {
            const response = await fetch('/api/geographic/countries');
            const data = await response.json();
            
            populateSelect(countrySelect, data, 'code', 'name', initialCountry);
            
            // Se c'è un paese iniziale, carica le regioni
            if (initialCountry) {
                await loadRegions(initialCountry, initialRegion);
            }
        } catch (error) {
            console.error('❌ Errore nel caricamento dei paesi:', error);
        } finally {
            showLoading(countrySelect, false);
        }
    }
    
    // Carica regioni per paese
    async function loadRegions(countryCode, selectedRegion = '') {
        showLoading(regionSelect, true);
        
        clearSelect(provinceSelect);
        clearSelect(citySelect);
        provinceContainer.classList.add('hidden');
        cityContainer.classList.add('hidden');
        
        try {
            const response = await fetch(`/api/geographic/regions/${countryCode}`);
            const data = await response.json();
            
            if (data.length > 0) {
                populateSelect(regionSelect, data, 'name', 'name', selectedRegion);
                regionContainer.classList.remove('hidden');
                
                // Se c'è una regione iniziale, carica province e città
                if (selectedRegion) {
                    await loadProvinces(countryCode, selectedRegion, initialProvince);
                    await loadCities(selectedRegion, initialProvince, initialCity);
                }
            }
        } catch (error) {
            console.error('❌ Errore nel caricamento delle regioni:', error);
        } finally {
            showLoading(regionSelect, false);
        }
    }
    
    // Carica province per regione (solo Italia)
    async function loadProvinces(countryCode, regionName, selectedProvince = '') {
        if (countryCode !== 'it') {
            provinceContainer.classList.add('hidden');
            return;
        }
        
        showLoading(provinceSelect, true);
        
        try {
            // Ottieni l'ID della regione dal nome
            const regionOption = Array.from(regionSelect.options).find(opt => opt.value === regionName);
            if (!regionOption) return;
            
            const regionId = regionOption.dataset.id;
            if (!regionId) return;
            
            const response = await fetch(`/api/geographic/provinces/${regionId}`);
            const data = await response.json();
            
            if (data.length > 0) {
                populateSelect(provinceSelect, data, 'name', 'name', selectedProvince, true);
                provinceContainer.classList.remove('hidden');
            }
        } catch (error) {
            console.error('❌ Errore nel caricamento delle province:', error);
        } finally {
            showLoading(provinceSelect, false);
        }
    }
    
    // Carica città per regione/provincia
    async function loadCities(regionName, provinceName = '', selectedCity = '') {
        showLoading(citySelect, true);
        
        try {
            // Ottieni l'ID della regione dal nome
            const regionOption = Array.from(regionSelect.options).find(opt => opt.value === regionName);
            if (!regionOption) return;
            
            const regionId = regionOption.dataset.id;
            if (!regionId) return;
            
            let url = `/api/geographic/cities/${regionId}`;
            
            // Se è selezionata una provincia, filtra per quella
            if (provinceName) {
                const provinceOption = Array.from(provinceSelect.options).find(opt => opt.value === provinceName);
                if (provinceOption && provinceOption.dataset.id) {
                    url += `?province_id=${provinceOption.dataset.id}`;
                }
            }
            
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.length > 0) {
                populateSelect(citySelect, data, 'name', 'name', selectedCity, true);
                cityContainer.classList.remove('hidden');
            }
        } catch (error) {
            console.error('❌ Errore nel caricamento delle città:', error);
        } finally {
            showLoading(citySelect, false);
        }
    }
    
    // Event listeners
    countrySelect.addEventListener('change', async function() {
        const countryCode = this.value;
        
        regionContainer.classList.add('hidden');
        provinceContainer.classList.add('hidden');
        cityContainer.classList.add('hidden');
        
        // Pulisci il CAP quando cambia il paese
        if (postalCodeInput) {
            postalCodeInput.value = '';
            postalCodeInput.classList.remove('input-success', 'input-error');
        }
        
        if (countryCode) {
            await loadRegions(countryCode);
        }
    });
    
    regionSelect.addEventListener('change', async function() {
        const regionName = this.value;
        const countryCode = countrySelect.value;
        
        provinceContainer.classList.add('hidden');
        cityContainer.classList.add('hidden');
        
        // Pulisci il CAP quando cambia la regione
        if (postalCodeInput) {
            postalCodeInput.value = '';
            postalCodeInput.classList.remove('input-success', 'input-error');
        }
        
        if (regionName) {
            if (countryCode === 'it') {
                await loadProvinces(countryCode, regionName);
            }
            await loadCities(regionName);
        }
    });
    
    provinceSelect.addEventListener('change', async function() {
        const provinceName = this.value;
        const regionName = regionSelect.value;
        
        cityContainer.classList.add('hidden');
        
        // Pulisci il CAP quando cambia la provincia
        if (postalCodeInput) {
            postalCodeInput.value = '';
            postalCodeInput.classList.remove('input-success', 'input-error');
        }
        
        if (regionName) {
            await loadCities(regionName, provinceName);
        }
    });
    
    // ========================================
    // POSTAL CODE AUTO-FILL LOGIC
    // ========================================
    
    let postalCodeLookupTimeout = null;
    
    /**
     * Show/hide loading indicator for postal code
     */
    function setPostalCodeLoading(isLoading) {
        if (postalCodeLoading) {
            if (isLoading) {
                postalCodeLoading.classList.remove('hidden');
                postalCodeLoading.classList.add('flex');
            } else {
                postalCodeLoading.classList.remove('flex');
                postalCodeLoading.classList.add('hidden');
            }
        }
        if (postalCodeInput) {
            postalCodeInput.disabled = isLoading;
        }
    }
    
    /**
     * Lookup geographic data from postal code (Italy only)
     */
    async function lookupPostalCode(postalCode) {
        const countryCode = (countrySelect.value || 'it').toLowerCase();
        
        // Solo per l'Italia per ora
        if (countryCode !== 'it') {
            return;
        }
        
        // Validazione formato CAP italiano (5 cifre)
        if (!/^\d{5}$/.test(postalCode)) {
            return;
        }
        
        setPostalCodeLoading(true);
        
        try {
            const response = await fetch(`/api/geographic/lookup/postal-code/${postalCode}?country=${countryCode}`);
            
            if (!response.ok) {
                return;
            }
            
            const data = await response.json();
            
            // Popola il paese
            countrySelect.value = countryCode;
            
            // Popola la regione
            if (data.region && data.region.name) {
                // Ricarica le regioni se necessario
                if (regionSelect.options.length <= 1) {
                    await loadRegions(countryCode, data.region.name);
                } else {
                    regionSelect.value = data.region.name;
                    regionContainer.classList.remove('hidden');
                }
                
                // Popola provincia e città
                if (data.province && data.province.name) {
                    // Carica le province per la regione
                    await loadProvinces(countryCode, data.region.name, data.province.name);
                    provinceSelect.value = data.province.name;
                    
                    // Aggiungi effetto visivo
                    provinceSelect.classList.add('select-success');
                    setTimeout(() => provinceSelect.classList.remove('select-success'), 2000);
                }
                
                // Carica le città
                await loadCities(data.region.name, data.province?.name || '', data.city?.name || '');
                
                if (data.city && data.city.name) {
                    citySelect.value = data.city.name;
                    
                    // Aggiungi effetto visivo
                    regionSelect.classList.add('select-success');
                    citySelect.classList.add('select-success');
                    setTimeout(() => {
                        regionSelect.classList.remove('select-success');
                        citySelect.classList.remove('select-success');
                    }, 2000);
                }
            }
            
        } catch (error) {
            console.error('❌ Errore nel lookup del CAP:', error);
        } finally {
            setPostalCodeLoading(false);
        }
    }
    
    /**
     * Auto-fill postal code when city is selected (if city has only one postal code)
     * Always clears the current postal code and updates based on the new city
     */
    async function autoFillPostalCodeForCity(cityName) {
        const countryCode = (countrySelect.value || 'it').toLowerCase();
        
        // Pulisci sempre il CAP quando cambia la città
        if (postalCodeInput) {
            postalCodeInput.value = '';
            postalCodeInput.classList.remove('input-success', 'input-error');
        }
        
        // Solo per l'Italia
        if (countryCode !== 'it' || !cityName) {
            return;
        }
        
        try {
            const response = await fetch(`/api/geographic/postal-codes-by-city?city=${encodeURIComponent(cityName)}&country=${countryCode}`);
            
            if (!response.ok) {
                return;
            }
            
            const postalCodes = await response.json();
            
            // Se c'è un solo CAP, inseriscilo automaticamente
            if (postalCodes && postalCodes.length === 1 && postalCodeInput) {
                postalCodeInput.value = postalCodes[0];
                postalCodeInput.classList.add('input-success');
                setTimeout(() => postalCodeInput.classList.remove('input-success'), 2000);
            }
            
        } catch (error) {
            console.error('❌ Errore nella ricerca CAP per città:', error);
        }
    }
    
    // Event listener per il campo CAP
    if (postalCodeInput) {
        postalCodeInput.addEventListener('blur', function() {
            const postalCode = this.value.trim();
            
            if (postalCode) {
                lookupPostalCode(postalCode);
            }
        });
        
        // Anche on input con debounce per una UX migliore
        postalCodeInput.addEventListener('input', function() {
            const postalCode = this.value.trim();
            
            // Clear previous timeout
            if (postalCodeLookupTimeout) {
                clearTimeout(postalCodeLookupTimeout);
            }
            
            // Solo se ha 5 cifre (CAP completo)
            if (/^\d{5}$/.test(postalCode)) {
                postalCodeLookupTimeout = setTimeout(() => {
                    lookupPostalCode(postalCode);
                }, 500);
            }
        });
    }
    
    // Event listener per la selezione della città
    citySelect.addEventListener('change', async function() {
        const cityName = this.value;
        const countryCode = countrySelect.value;
        
        // Se il paese è Italia e la città è selezionata
        if (countryCode === 'it' && cityName) {
            // Ottieni l'option selezionata per recuperare il province_id
            const selectedOption = this.options[this.selectedIndex];
            const provinceId = selectedOption?.dataset.provinceId;
            
            // Se la città ha una provincia e la provincia non è stata ancora selezionata
            if (provinceId && !provinceSelect.value) {
                try {
                    const regionName = regionSelect.value;
                    
                    // Se le province non sono state caricate, caricale
                    if (provinceSelect.options.length <= 1) {
                        await loadProvinces(countryCode, regionName);
                    }
                    
                    // Trova la provincia corrispondente nell'elenco
                    const provinceOption = Array.from(provinceSelect.options).find(
                        opt => opt.dataset.id === provinceId
                    );
                    
                    if (provinceOption) {
                        provinceSelect.value = provinceOption.value;
                        
                        // Ricarica le città filtrate per questa provincia, mantenendo la città selezionata
                        await loadCities(regionName, provinceOption.value, cityName);
                    }
                } catch (error) {
                    console.error('❌ Errore nell\'auto-selezione della provincia:', error);
                }
            }
        }
        
        // Chiama sempre la funzione per gestire il CAP (anche se cityName è vuoto)
        autoFillPostalCodeForCity(cityName);
    });
    
    // ========================================
    // END POSTAL CODE LOGIC
    // ========================================
    
    // Inizializzazione
    loadCountries();
})();
</script>