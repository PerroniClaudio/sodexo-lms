<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuova unità lavorativa')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.job-units.store') }}" class="flex flex-col gap-6">
                    @csrf

                    <div class="form-control flex flex-col gap-2">
                        <label for="name" class="label p-0">
                            <span class="label-text font-medium">{{ __('Nome') }}</span>
                        </label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            class="input input-bordered w-full @error('name') input-error @enderror"
                            required
                        >
                        @error('name')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label for="unit_code" class="label p-0">
                            <span class="label-text font-medium">{{ __('Codice Unità') }}</span>
                            <span class="label-text-alt text-base-content/60">{{ __('Opzionale') }}</span>
                        </label>
                        <input
                            id="unit_code"
                            name="unit_code"
                            type="text"
                            value="{{ old('unit_code') }}"
                            class="input input-bordered w-full @error('unit_code') input-error @enderror"
                            placeholder="{{ __('Es: MI001, RM042') }}"
                        >
                        @error('unit_code')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Selezione geografica gerarchica -->
                    <x-address-selector-simple 
                        :countryValue="old('country', 'it')"
                        :regionValue="old('region')"
                        :provinceValue="old('province')"
                        :cityValue="old('city')"
                        :addressValue="old('address')"
                        :postalCodeValue="old('postal_code')"
                        :required="true"
                    />

                    <div class="form-control flex flex-col gap-2">
                        <label for="description" class="label p-0">
                            <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            class="textarea textarea-bordered w-full @error('description') textarea-error @enderror"
                        >{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.job-units.index') }}" class="btn btn-ghost">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva e continua') }}</span>
                            <x-lucide-arrow-right class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
