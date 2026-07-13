@props(['data' => []])

@php
    extract($data);
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div class="form-control flex flex-col gap-2">
        <label for="name" class="label p-0">
            <span class="label-text font-medium">{{ __('Nome') }}</span>
        </label>
        <input id="name" name="name" type="text" value="{{ old('name', $languageLevel->name ?? '') }}" class="input input-bordered w-full @error('name') input-error @enderror" required>
        @error('name')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
        {{ __('L\'ordine viene gestito dalla lista tramite drag and drop.') }}
    </div>

</div>
