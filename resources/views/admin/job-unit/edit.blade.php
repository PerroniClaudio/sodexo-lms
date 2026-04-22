<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica unità lavorativa')">
            <x-slot:actions>
                @if($unit->trashed())
                    <form method="POST" action="{{ route('admin.job-units.restore', $unit->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-outline">
                            <x-lucide-refresh-cw class="h-4 w-4" />
                            <span>{{ __('Ripristina unità') }}</span>
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.job-units.destroy', $unit) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questa unità lavorativa?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error btn-outline">
                            <x-lucide-trash-2 class="h-4 w-4" />
                            <span>{{ __('Elimina unità') }}</span>
                        </button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="card-title">{{ __('Dati anagrafici') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Gestisci le unità lavorative.') }}
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.job-units.update', $unit) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <div class="form-control flex flex-col gap-2">
                        <label for="name" class="label font-semibold p-0">
                            <span class="label-text font-medium">{{ __('Nome') }}</span>
                        </label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name', $unit->name) }}"
                            class="input input-bordered w-full @error('name') input-error @enderror"
                            required
                        >
                        @error('name')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Selezione geografica gerarchica -->
                    <x-address-selector-simple 
                        :countryValue="old('country', $unit->country?->code ?: 'it')"
                        :regionValue="old('region', $unit->region?->name)"
                        :provinceValue="old('province', $unit->province?->name)"
                        :cityValue="old('city', $unit->city?->name)"
                        :addressValue="old('address', $unit->address)"
                        :postalCodeValue="old('postal_code', $unit->postal_code)"
                        :required="true"
                    />

                    <div class="form-control flex flex-col gap-2">
                        <label for="description" class="label font-semibold p-0">
                            <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            class="textarea textarea-bordered w-full @error('description') textarea-error @enderror"
                        >{{ old('description', $unit->description) }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Salva dati') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
