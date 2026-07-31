@props(['data' => []])

@php
    extract($data);
@endphp

<div class="card border border-base-300 bg-base-100 shadow-sm" data-dispense-module>
    <div class="card-body gap-5">
        <div>
            <h2 class="text-xl font-semibold">{{ __('Dispense') }}</h2>
            <p class="text-sm text-base-content/70">{{ __('Scarica tutti i file per poter proseguire.') }}</p>
        </div>

        <div class="grid gap-3">
            @foreach ($module->teachingMaterials as $material)
                <div class="flex flex-col gap-3 rounded-box border border-base-300 p-4 sm:flex-row sm:items-center sm:justify-between" data-dispense-material="{{ $material->id }}">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="truncate font-medium">{{ $material->original_name }}</p>
                            <span class="badge badge-ghost badge-sm" data-dispense-material-status>{{ __('Da scaricare') }}</span>
                        </div>
                        <p class="text-sm text-base-content/60">{{ $material->mime_type ?: __('File') }} · {{ Illuminate\Support\Number::fileSize($material->size_bytes) }}</p>
                    </div>
                    <a
                        href="{{ route('user.courses.modules.dispense.download', [$course, $module, $material]) }}"
                        class="btn btn-outline btn-primary btn-sm"
                        data-dispense-download
                    >
                        <x-lucide-download class="h-4 w-4" />
                        <span>{{ __('Scarica') }}</span>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="hidden rounded-box border border-warning/40 bg-warning/10 p-4 text-center" data-dispense-waiting>
            <p class="text-sm text-base-content/75">{{ __('Hai scaricato tutti i file. Potrai proseguire al termine del tempo minimo.') }}</p>
            <p class="mt-2 text-2xl font-semibold text-warning" data-dispense-timer>00:00:00</p>
        </div>

        <div class="hidden justify-end" data-dispense-proceed>
            <button type="button" class="btn btn-primary" data-dispense-complete>
                <span>{{ __('Prosegui') }}</span>
                <x-lucide-arrow-right class="h-4 w-4" />
            </button>
        </div>

        <div class="hidden alert alert-error" data-dispense-error></div>
    </div>
</div>
