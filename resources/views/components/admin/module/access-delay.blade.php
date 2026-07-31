@props(['data' => []])

@php
    extract($data);
@endphp

<div class="grid gap-2">
    <label for="access_delay_minutes" class="label p-0">
        <span class="label-text font-medium">{{ __('Tempo di attesa') }}</span>
    </label>
    <input
        id="access_delay_minutes"
        name="access_delay_minutes"
        type="number"
        min="0"
        value="{{ old('access_delay_minutes', $module->access_delay_minutes) }}"
        class="input input-bordered w-full @error('access_delay_minutes') input-error @enderror"
    >
    <span class="text-sm text-base-content/70">{{ __('Minuti da attendere dal completamento del modulo precedente prima di accedere al modulo.') }}</span>
    @error('access_delay_minutes')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>
