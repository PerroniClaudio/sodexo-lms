@props(['data' => []])

@php
    extract($data);

    $minimumDuration = (int) $module->minimum_duration_seconds;
    $minimumDurationHours = old('minimum_duration_hours', intdiv($minimumDuration, 3600));
    $minimumDurationMinutes = old('minimum_duration_minutes', intdiv($minimumDuration % 3600, 60));
    $minimumDurationSeconds = old('minimum_duration_seconds', $minimumDuration % 60);
@endphp

<x-admin.module.validity-badge :data="get_defined_vars()" />
<x-admin.module.editable-title :data="get_defined_vars()" />
<x-admin.module.description :data="get_defined_vars()" />
<x-admin.module.status :data="get_defined_vars()" />

<fieldset class="grid gap-4 rounded-box border border-base-300 p-4">
    <legend class="px-2 text-sm font-semibold">{{ __('Tempo minimo dopo i download') }}</legend>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            'minimum_duration_hours' => [__('Ore'), $minimumDurationHours, 1193045],
            'minimum_duration_minutes' => [__('Minuti'), $minimumDurationMinutes, 59],
            'minimum_duration_seconds' => [__('Secondi'), $minimumDurationSeconds, 59],
        ] as $field => [$label, $value, $max])
            <label class="form-control">
                <span class="label-text mb-2">{{ $label }}</span>
                <input
                    type="number"
                    name="{{ $field }}"
                    value="{{ $value }}"
                    min="0"
                    @if ($max !== null) max="{{ $max }}" @endif
                    class="input input-bordered w-full @error($field) input-error @enderror"
                    required
                >
                @error($field)
                    <span class="mt-1 text-sm text-error">{{ $message }}</span>
                @enderror
            </label>
        @endforeach
    </div>
    <p class="text-sm text-base-content/70">{{ __('Il conteggio parte dopo il primo download di tutti i file. Lascia tutti i valori a zero per consentire di proseguire subito.') }}</p>
</fieldset>
