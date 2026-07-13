@props(['data' => []])

@php
    extract($data);
@endphp

<div class="grid gap-6 md:grid-cols-2">
    <div class="form-control flex flex-col gap-2 md:col-span-2">
        <label for="company_name" class="label p-0">
            <span class="label-text font-medium">{{ __('Ragione Sociale') }}</span>
        </label>
        <input id="company_name" name="company_name" type="text" value="{{ old('company_name', $fundingEntity->company_name ?? '') }}" class="input input-bordered w-full @error('company_name') input-error @enderror" required>
        @error('company_name')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-control flex flex-col gap-2">
        <label for="vat_number" class="label p-0">
            <span class="label-text font-medium">{{ __('Partita IVA') }}</span>
        </label>
        <input id="vat_number" name="vat_number" type="text" value="{{ old('vat_number', $fundingEntity->vat_number ?? '') }}" class="input input-bordered w-full @error('vat_number') input-error @enderror">
        @error('vat_number')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-control flex flex-col gap-2">
        <label for="fiscal_code" class="label p-0">
            <span class="label-text font-medium">{{ __('Codice Fiscale') }}</span>
        </label>
        <input id="fiscal_code" name="fiscal_code" type="text" value="{{ old('fiscal_code', $fundingEntity->fiscal_code ?? '') }}" class="input input-bordered w-full @error('fiscal_code') input-error @enderror">
        @error('fiscal_code')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-control flex flex-col gap-2 md:col-span-2">
        <label for="pec" class="label p-0">
            <span class="label-text font-medium">{{ __('PEC') }}</span>
        </label>
        <input id="pec" name="pec" type="email" value="{{ old('pec', $fundingEntity->pec ?? '') }}" class="input input-bordered w-full @error('pec') input-error @enderror">
        @error('pec')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>
</div>
