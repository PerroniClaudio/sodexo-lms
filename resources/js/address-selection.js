// address-selection.js
// Vanilla JS per address-selector.blade.php (senza Alpine)

(function() {
    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function AddressSelector(root) {
        // Elementi
        const countrySelect = root.querySelector('[name="country"]');
        const regionSelect = root.querySelector('[name="region"]');
        const provinceSelect = root.querySelector('[name="province"]');
        const citySelect = root.querySelector('[name="city"]');
        const addressInput = root.querySelector('[name="address"]');
        const postalCodeInput = root.querySelector('[name="postal_code"]');
        const searchCountryInput = root.querySelector('input[type="search"][placeholder*="paese"]');
        const searchRegionInput = root.querySelector('input[type="search"][placeholder*="regione"]');
        const searchProvinceInput = root.querySelector('input[type="search"][placeholder*="provincia"]');
        const searchCityInput = root.querySelector('input[type="search"][placeholder*="città"]');
        const loadingCountries = root.querySelector('.loading-spinner[loading-countries]') || root.querySelector('.loading-spinner');
        const loadingRegions = root.querySelector('.loading-spinner[loading-regions]') || root.querySelector('.loading-spinner');
        const loadingProvinces = root.querySelector('.loading-spinner[loading-provinces]') || root.querySelector('.loading-spinner');
        const loadingCities = root.querySelector('.loading-spinner[loading-cities]') || root.querySelector('.loading-spinner');

        // Stato
        let countries = [];
        let regions = [];
        let provinces = [];
        let cities = [];
        let selectedCountry = countrySelect?.value || '';
        let selectedRegion = regionSelect?.value || '';
        let selectedProvince = provinceSelect?.value || '';
        let selectedCity = citySelect?.value || '';
        let searchCountry = '';
        let searchRegion = '';
        let searchProvince = '';
        let searchCity = '';

        // Funzioni di caricamento
        async function loadCountries() {
            if (loadingCountries) loadingCountries.style.display = '';
            try {
                const response = await fetch(`/api/geographic/countries?search=${encodeURIComponent(searchCountry)}&locale=it`);
                const data = await response.json();
                countries = data.map(country => ({ value: country.code, label: country.name }));
                countrySelect.innerHTML = `<option value="">Seleziona un paese...</option>` + countries.map(c => `<option value="${c.value}"${selectedCountry === c.value ? ' selected' : ''}>${c.label}</option>`).join('');
            } catch (e) { console.error(e); }
            if (loadingCountries) loadingCountries.style.display = 'none';
        }
        async function loadRegions() {
            if (!selectedCountry) { regions = []; regionSelect.innerHTML = `<option value="">Seleziona una regione...</option>`; return; }
            if (loadingRegions) loadingRegions.style.display = '';
            try {
                const response = await fetch(`/api/geographic/regions/${encodeURIComponent(selectedCountry)}?search=${encodeURIComponent(searchRegion)}&locale=it`);
                const data = await response.json();
                regions = data.map(region => ({ value: region.id, label: region.name, division_id: region.id }));
                regionSelect.innerHTML = `<option value="">Seleziona una regione...</option>` + regions.map(r => `<option value="${r.value}"${selectedRegion === r.value ? ' selected' : ''}>${r.label}</option>`).join('');
            } catch (e) { console.error(e); }
            if (loadingRegions) loadingRegions.style.display = 'none';
        }
        async function loadProvinces() {
            if (!selectedRegion || selectedCountry !== 'IT') { provinces = []; provinceSelect.innerHTML = `<option value="">Seleziona una provincia...</option>`; return; }
            if (loadingProvinces) loadingProvinces.style.display = '';
            try {
                const response = await fetch(`/api/geographic/provinces/${encodeURIComponent(selectedRegion)}?search=${encodeURIComponent(searchProvince)}&locale=it`);
                const data = await response.json();
                provinces = data.map(province => ({ value: province.id, label: province.name }));
                provinceSelect.innerHTML = `<option value="">Seleziona una provincia...</option>` + provinces.map(p => `<option value="${p.value}"${selectedProvince === p.value ? ' selected' : ''}>${p.label}</option>`).join('');
            } catch (e) { console.error(e); }
            if (loadingProvinces) loadingProvinces.style.display = 'none';
        }
        async function loadCities() {
            if (!selectedRegion) { cities = []; citySelect.innerHTML = `<option value="">Seleziona una città...</option>`; return; }
            const divisionId = selectedProvince || selectedRegion;
            if (loadingCities) loadingCities.style.display = '';
            try {
                const response = await fetch(`/api/geographic/cities/${encodeURIComponent(divisionId)}?search=${encodeURIComponent(searchCity)}&locale=it`);
                const data = await response.json();
                cities = data.map(city => ({ value: city.id, label: city.name }));
                citySelect.innerHTML = `<option value="">Seleziona una città...</option>` + cities.map(c => `<option value="${c.value}"${selectedCity === c.value ? ' selected' : ''}>${c.label}</option>`).join('');
            } catch (e) { console.error(e); }
            if (loadingCities) loadingCities.style.display = 'none';
        }

        // Eventi
        if (countrySelect) countrySelect.addEventListener('change', function() {
            selectedCountry = this.value;
            selectedRegion = '';
            selectedProvince = '';
            selectedCity = '';
            loadRegions();
            loadProvinces();
            loadCities();
        });
        if (regionSelect) regionSelect.addEventListener('change', function() {
            selectedRegion = this.value;
            selectedProvince = '';
            selectedCity = '';
            loadProvinces();
            loadCities();
        });
        if (provinceSelect) provinceSelect.addEventListener('change', function() {
            selectedProvince = this.value;
            selectedCity = '';
            loadCities();
        });
        if (searchCountryInput) searchCountryInput.addEventListener('input', debounce(function() {
            searchCountry = this.value;
            loadCountries();
        }, 300));
        if (searchRegionInput) searchRegionInput.addEventListener('input', debounce(function() {
            searchRegion = this.value;
            loadRegions();
        }, 300));
        if (searchProvinceInput) searchProvinceInput.addEventListener('input', debounce(function() {
            searchProvince = this.value;
            loadProvinces();
        }, 300));
        if (searchCityInput) searchCityInput.addEventListener('input', debounce(function() {
            searchCity = this.value;
            loadCities();
        }, 300));

        // Inizializzazione
        loadCountries().then(() => {
            if (selectedCountry) {
                loadRegions().then(() => {
                    if (selectedRegion) {
                        if (selectedCountry === 'IT') loadProvinces();
                        loadCities();
                    }
                });
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-address-selector]').forEach(AddressSelector);
    });
})();
