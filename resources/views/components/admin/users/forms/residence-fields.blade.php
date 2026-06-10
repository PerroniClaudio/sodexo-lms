@props([
    'user' => null,
])

<div class="flex flex-col gap-4" data-user-only-block>
    @error('geography')<span class="text-error text-sm">{{ $message }}</span>@enderror

    <x-address-selector-simple
        :countryValue="old('country', $user->homeCountry?->code ?? 'it')"
        :regionValue="old('region', $user->homeRegion?->name ?? '')"
        :provinceValue="old('province', $user->homeProvince?->name ?? '')"
        :cityValue="old('city', $user->homeCity?->name ?? '')"
        :addressValue="old('address', $user->address ?? '')"
        :postalCodeValue="old('postal_code', $user->postal_code ?? '')"
        :required="false"
    />
</div>
