@props([
    'name',
    'id' => null,
    'required' => false,
    'selectedValue' => null,
    'options' => [],
    'label',
    'placeholder' => 'Cerca o seleziona...',
])

@php
    $fieldId = $id ?? $name;
    $selectedOption = collect($options)->firstWhere('value', old($name, $selectedValue));
    $inputValue = $selectedOption['label'] ?? '';
    $uniqueId = $fieldId . '_' . uniqid();
@endphp

<div class="form-control flex flex-col gap-2" {{ $attributes }}>
    <label for="{{ $uniqueId }}_input" class="label">
        <span class="label-text font-medium">
            {{ $label }}
            @if ($required)
                <span class="text-error">*</span>
            @endif
        </span>
    </label>

    <div class="relative" data-searchable-select="{{ $uniqueId }}">
        <input
            type="text"
            id="{{ $uniqueId }}_input"
            class="input input-bordered w-full @error($name) input-error @enderror"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
            value="{{ old($name.'_display', $inputValue) }}"
            data-input
            data-required="{{ $required ? 'true' : 'false' }}"
            @required($required)
        >

        <div class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
            <svg class="h-4 w-4 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>

        <div
            class="absolute z-50 mt-1 hidden max-h-60 w-full overflow-auto rounded-box border border-base-300 bg-base-100 shadow-lg"
            data-dropdown
        >
            <ul class="menu menu-sm w-full p-0">
                @foreach ($options as $option)
                    <li
                        class="w-full"
                        data-option-value="{{ $option['value'] }}"
                        data-option-label="{{ $option['label'] }}"
                        data-search-text="{{ $option['search'] ?? $option['label'] }}"
                    >
                        <a class="flex w-full items-start gap-2">
                            @if (!empty($option['badge']))
                                <code class="badge badge-sm badge-ghost shrink-0">{{ $option['badge'] }}</code>
                            @endif
                            <span class="flex-1">
                                <span class="block truncate">{{ $option['label'] }}</span>
                                @if (!empty($option['description']))
                                    <span class="block text-xs text-base-content/60">{{ $option['description'] }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <input
            type="hidden"
            id="{{ $fieldId }}"
            name="{{ $name }}"
            value="{{ old($name, $selectedValue) }}"
            data-hidden
        >
    </div>

    @error($name)
        <span class="mt-1 text-sm text-error">{{ $message }}</span>
    @enderror
</div>

@once
    @push('scripts')
    <script>
    (function() {
        function initSearchableSelect(root) {
            const input = root.querySelector('[data-input]');
            const dropdown = root.querySelector('[data-dropdown]');
            const hiddenInput = root.querySelector('[data-hidden]');
            const optionItems = root.querySelectorAll('[data-option-value]');
            const isRequired = input?.dataset.required === 'true';

            if (!input || !dropdown || !hiddenInput) {
                return;
            }

            let isOpen = false;

            function syncValidity() {
                if (!isRequired || input.disabled) {
                    input.setCustomValidity('');

                    return;
                }

                input.setCustomValidity(hiddenInput.value === '' ? 'Seleziona un valore dalla lista.' : '');
            }

            function setHiddenValue(value) {
                if (hiddenInput.value === value) {
                    return;
                }

                hiddenInput.value = value;
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function toggleDropdown(show) {
                isOpen = show;

                if (show) {
                    dropdown.classList.remove('hidden');

                    return;
                }

                dropdown.classList.add('hidden');
                optionItems.forEach(function(item) {
                    item.classList.remove('hidden');
                });
            }

            input.addEventListener('click', function() {
                toggleDropdown(true);
            });

            input.addEventListener('focus', function() {
                toggleDropdown(true);
            });

            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();

                optionItems.forEach(function(item) {
                    const searchText = (item.dataset.searchText || '').toLowerCase();
                    item.classList.toggle('hidden', !searchText.includes(searchTerm));
                });

                toggleDropdown(true);

                if (searchTerm !== '') {
                    const exactMatch = Array.from(optionItems).find(function(item) {
                        return (item.dataset.searchText || '').toLowerCase() === searchTerm;
                    });

                    if (!exactMatch) {
                        setHiddenValue('');
                    }
                }

                syncValidity();
            });

            optionItems.forEach(function(item) {
                item.querySelector('a')?.addEventListener('click', function(event) {
                    event.preventDefault();

                    input.value = item.dataset.optionLabel;
                    setHiddenValue(item.dataset.optionValue);
                    syncValidity();
                    toggleDropdown(false);
                });
            });

            document.addEventListener('click', function(event) {
                if (!root.contains(event.target)) {
                    toggleDropdown(false);
                }
            });

            input.addEventListener('keydown', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    syncValidity();
                } else if (event.key === 'Escape') {
                    toggleDropdown(false);
                } else if (event.key === 'ArrowDown' && !isOpen) {
                    toggleDropdown(true);
                    event.preventDefault();
                }
            });

            input.addEventListener('blur', syncValidity);
            root.closest('form')?.addEventListener('submit', syncValidity);
            syncValidity();
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-searchable-select]').forEach(initSearchableSelect);
        });
    })();
    </script>
    @endpush
@endonce
