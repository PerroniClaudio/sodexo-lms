@props([
    'name' => 'job_unit_id',
    'id' => 'job_unit_id',
    'required' => false,
    'selectedId' => null,
    'units' => [],
    'label' => 'Unità Produttiva',
    'placeholder' => 'Cerca o seleziona un\'unità produttiva...',
])

@php
    $selectedUnit = collect($units)->firstWhere('id', $selectedId);
    $inputValue = $selectedUnit ? ($selectedUnit->unit_code ? "{$selectedUnit->unit_code} - {$selectedUnit->name}" : $selectedUnit->name) : '';
    $uniqueId = $id . '_' . uniqid();
@endphp

<div class="form-control" {{ $attributes }}>
    <label for="{{ $uniqueId }}_input" class="label">
        <span class="label-text font-semibold">
            {{ $label }}
            @if($required)
                <span class="text-error">*</span>
            @endif
        </span>
    </label>
    
    <div class="relative" data-job-unit-selector="{{ $uniqueId }}">
        <!-- Input visibile -->
        <input
            type="text"
            id="{{ $uniqueId }}_input"
            class="input input-bordered w-full @error($name) input-error @enderror"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            value="{{ old($name.'_display', $inputValue) }}"
            data-input
        >
        
        <!-- Icona dropdown -->
        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
            <svg class="h-4 w-4 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        
        <!-- Dropdown menu -->
        <div 
            class="absolute z-50 mt-1 hidden w-full max-h-60 overflow-auto rounded-box border border-base-300 bg-base-100 shadow-lg"
            data-dropdown
        >
            <ul class="menu menu-sm p-0 w-full">
                @foreach($units as $unit)
                    <li class="w-full" data-unit-id="{{ $unit->id }}" data-unit-label="@if($unit->unit_code){{ $unit->unit_code }} - @endif{{ $unit->name }}" data-search-text="@if($unit->unit_code){{ $unit->unit_code }} @endif{{ $unit->name }}">
                        <a class="flex items-center gap-2 w-full">
                            @if($unit->unit_code)
                                <code class="badge badge-sm badge-ghost shrink-0">{{ $unit->unit_code }}</code>
                            @endif
                            <span class="flex-1 truncate">{{ $unit->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        
        <!-- Hidden input per l'invio del valore -->
        <input
            type="hidden"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ old($name, $selectedId) }}"
            data-hidden
            @if($required) required @endif
        >
    </div>
    
    @error($name)
        <span class="text-error text-sm mt-1">{{ $message }}</span>
    @enderror
</div>

@once
    @push('scripts')
    <script>
    (function() {
        function initJobUnitSelector(root) {
            const input = root.querySelector('[data-input]');
            const dropdown = root.querySelector('[data-dropdown]');
            const hiddenInput = root.querySelector('[data-hidden]');
            const menuItems = root.querySelectorAll('[data-unit-id]');
            
            if (!input || !dropdown || !hiddenInput) {
                return;
            }
            
            let isOpen = false;
            
            // Apri dropdown al click sull'input
            input.addEventListener('click', function() {
                toggleDropdown(true);
            });
            
            // Apri dropdown al focus
            input.addEventListener('focus', function() {
                toggleDropdown(true);
            });
            
            // Filtra mentre l'utente digita
            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                let hasVisibleItems = false;
                
                menuItems.forEach(function(item) {
                    // Usa data-search-text per la ricerca (senza " - ")
                    const searchText = (item.dataset.searchText || '').toLowerCase();
                    const matches = searchText.includes(searchTerm);
                    
                    if (matches) {
                        item.classList.remove('hidden');
                        hasVisibleItems = true;
                    } else {
                        item.classList.add('hidden');
                    }
                });
                
                // Se non ci sono risultati, mostra comunque il dropdown
                toggleDropdown(true);
                
                // Reset del valore hidden se l'utente sta digitando
                if (searchTerm !== '') {
                    const exactMatch = Array.from(menuItems).find(item => {
                        const searchText = (item.dataset.searchText || '').toLowerCase();
                        return searchText === searchTerm;
                    });
                    if (!exactMatch) {
                        hiddenInput.value = '';
                    }
                }
            });
            
            // Gestisci selezione
            menuItems.forEach(function(item) {
                const link = item.querySelector('a');
                if (link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const unitId = item.dataset.unitId;
                        const unitLabel = item.dataset.unitLabel;
                        
                        input.value = unitLabel;
                        hiddenInput.value = unitId;
                        toggleDropdown(false);
                    });
                }
            });
            
            // Chiudi dropdown al click fuori
            document.addEventListener('click', function(e) {
                if (!root.contains(e.target)) {
                    toggleDropdown(false);
                }
            });
            
            // Gestione tastiera
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    toggleDropdown(false);
                } else if (e.key === 'ArrowDown' && !isOpen) {
                    toggleDropdown(true);
                    e.preventDefault();
                }
            });
            
            function toggleDropdown(show) {
                isOpen = show;
                if (show) {
                    dropdown.classList.remove('hidden');
                } else {
                    dropdown.classList.add('hidden');
                    // Mostra tutti gli elementi quando si chiude
                    menuItems.forEach(function(item) {
                        item.classList.remove('hidden');
                    });
                }
            }
        }
        
        // Inizializza tutti i selettori presenti nella pagina
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-job-unit-selector]').forEach(initJobUnitSelector);
        });
    })();
    </script>
    @endpush
@endonce
