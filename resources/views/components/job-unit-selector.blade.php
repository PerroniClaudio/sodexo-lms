@props([
    'name' => 'job_unit_id',
    'id' => 'job_unit_id',
    'required' => false,
    'selectedId' => null,
    'units' => [],
    'label' => 'UnitÃ  Produttiva',
    'placeholder' => 'Cerca o seleziona un\'unitÃ  produttiva...',
])

@php
    $options = collect($units)->map(function ($unit) {
        $label = $unit->unit_code ? "{$unit->unit_code} - {$unit->name}" : $unit->name;

        return [
            'value' => (string) $unit->id,
            'label' => $label,
            'search' => trim(($unit->unit_code ? $unit->unit_code.' ' : '').$unit->name),
            'badge' => $unit->unit_code,
        ];
    })->values()->all();
@endphp

<x-searchable-select
    :name="$name"
    :id="$id"
    :required="$required"
    :selected-value="$selectedId"
    :options="$options"
    :label="$label"
    :placeholder="$placeholder"
    {{ $attributes }}
/>
