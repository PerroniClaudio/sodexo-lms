@props(['companyDivision'])

@php
    $isEditing = $companyDivision->exists;
@endphp

<form method="POST" action="{{ $isEditing ? route('admin.company-divisions.update', $companyDivision) : route('admin.company-divisions.store') }}" enctype="multipart/form-data" class="flex flex-col gap-6">
    @csrf
    @if ($isEditing)
        @method('PUT')
    @endif

    <section class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="form-control flex flex-col gap-2">
                <label for="name" class="label p-0">
                    <span class="label-text font-medium">{{ __('Nome') }}</span>
                </label>
                <input id="name" name="name" type="text" value="{{ old('name', $companyDivision->name) }}" class="input input-bordered w-full @error('name') input-error @enderror" required>
                @error('name')<p class="text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-control flex flex-col gap-2">
                <label for="vat_number" class="label p-0">
                    <span class="label-text font-medium">{{ __('Partita IVA') }}</span>
                    <span class="label-text-alt text-base-content/60">{{ __('Opzionale') }}</span>
                </label>
                <input id="vat_number" name="vat_number" type="text" value="{{ old('vat_number', $companyDivision->vat_number) }}" class="input input-bordered w-full @error('vat_number') input-error @enderror">
                @error('vat_number')<p class="text-sm text-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-control flex flex-col gap-2">
                <label for="logo" class="label p-0">
                    <span class="label-text font-medium">{{ __('Logo') }}</span>
                    <span class="label-text-alt text-base-content/60">{{ __('JPG, PNG, WEBP o SVG') }}</span>
                </label>
                @if ($companyDivision->logoUrl())
                    <img src="{{ $companyDivision->logoUrl() }}" alt="{{ __('Logo divisione') }}" class="max-h-16 max-w-48 object-contain">
                @endif
                <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.webp,.svg" class="file-input file-input-bordered w-full @error('logo') file-input-error @enderror">
                @error('logo')<p class="text-sm text-error">{{ $message }}</p>@enderror
            </div>
        </div>
    </section>

    <div class="flex justify-end gap-3">
        @unless ($isEditing)
            <a href="{{ route('admin.company-divisions.index') }}" class="btn btn-ghost">{{ __('Annulla') }}</a>
        @endunless
        <button type="submit" class="btn btn-primary">
            <x-lucide-save class="h-4 w-4" />
            <span>{{ $isEditing ? __('Salva dati') : __('Salva') }}</span>
        </button>
    </div>
</form>
