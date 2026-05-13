@php
    $selectedCourseIds = collect(old('course_ids', $certificate->course_ids ?? []))
        ->map(fn ($value) => (string) $value)
        ->values();
    $associationMode = old('association_mode', $selectedCourseIds->isEmpty() ? 'generic' : 'specific');
@endphp

<div class="flex flex-col gap-6">
    <div class="form-control flex flex-col gap-2">
        <label for="type" class="label p-0">
            <span class="label-text font-medium">{{ __('Tipo attestato') }}</span>
        </label>
        <select
            id="type"
            name="type"
            class="select select-bordered w-full @error('type') select-error @enderror"
            required
        >
            <option value="">{{ __('Seleziona un tipo') }}</option>
            @foreach ($typeLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $certificate->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="form-control flex flex-col gap-2">
    <label for="template" class="label p-0">
        <span class="label-text font-medium">{{ __('Template DOCX') }}</span>
    </label>
    <input
        id="template"
        name="template"
        type="file"
        accept=".docx"
        class="file-input file-input-bordered w-full @error('template') file-input-error @enderror"
        @required($requireTemplateUpload)
    >
    @if ($certificate->exists)
        <p class="text-sm text-base-content/70">
            {{ __('File attuale: :file', ['file' => $certificate->original_filename]) }}
        </p>
    @endif
    <p class="text-sm text-base-content/70">
        {{ __('Inserire i placeholder senza spezzare la formattazione del testo nel documento Word.') }}
    </p>
    @error('template')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>

<div class="form-control flex flex-col gap-3">
    <label class="label p-0">
        <span class="label-text font-medium">{{ __('Ambito del template') }}</span>
    </label>
    <div class="flex flex-col gap-4 rounded-box border border-base-300 p-4" data-certificate-association-root>
        <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 px-4 py-3">
            <input
                type="radio"
                name="association_mode"
                value="generic"
                class="radio radio-sm"
                @checked($associationMode === 'generic')
                data-certificate-association-toggle
            >
            <span class="label-text">
                <span class="block font-medium">{{ __('Template generico') }}</span>
                <span class="block text-sm text-base-content/70">
                    {{ __('Viene usato come default.') }}
                </span>
            </span>
        </label>

        <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 px-4 py-3">
            <input
                type="radio"
                name="association_mode"
                value="specific"
                class="radio radio-sm"
                @checked($associationMode === 'specific')
                data-certificate-association-toggle
            >
            <span class="label-text">
                <span class="block font-medium">{{ __('Corsi specifici') }}</span>
                <span class="block text-sm text-base-content/70">
                    {{ __('Associa il template a uno o più corsi selezionati.') }}
                </span>
            </span>
        </label>

        <div
            class="@class(['hidden' => $associationMode !== 'specific'])"
            data-certificate-association-courses
        >
            <div class="grid gap-3 rounded-box border border-base-300 p-4 md:grid-cols-2">
                @forelse ($courses as $course)
                    <label class="label cursor-pointer justify-start gap-3 rounded-box border border-base-300 px-3 py-2">
                        <input
                            type="checkbox"
                            name="course_ids[]"
                            value="{{ $course->id }}"
                            class="checkbox checkbox-sm"
                            @checked($selectedCourseIds->contains((string) $course->id))
                        >
                        <span class="label-text">{{ $course->title }}</span>
                    </label>
                @empty
                    <p class="text-sm text-base-content/70">{{ __('Nessun corso disponibile.') }}</p>
                @endforelse
            </div>
        </div>
    </div>
    @error('course_ids')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
    @error('course_ids.*')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>

<div class="card border border-base-300 bg-base-200/40 shadow-sm">
    <div class="card-body gap-4">
        <h2 class="card-title text-base">{{ __('Variabili disponibili') }}</h2>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($placeholders as $placeholder => $description)
                <div class="rounded-box border border-base-300 bg-base-100 p-3">
                    <p class="font-mono text-sm font-semibold">{{ $placeholder }}</p>
                    <p class="text-sm text-base-content/70">{{ $description }}</p>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('[data-certificate-association-root]');

        if (!(root instanceof HTMLElement)) {
            return;
        }

        const toggles = root.querySelectorAll('[data-certificate-association-toggle]');
        const coursesPanel = root.querySelector('[data-certificate-association-courses]');

        if (!(coursesPanel instanceof HTMLElement)) {
            return;
        }

        const syncAssociationMode = () => {
            const selectedToggle = Array.from(toggles).find((toggle) => toggle instanceof HTMLInputElement && toggle.checked);
            const isSpecific = selectedToggle instanceof HTMLInputElement && selectedToggle.value === 'specific';

            coursesPanel.classList.toggle('hidden', !isSpecific);

            coursesPanel.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
                if (!(checkbox instanceof HTMLInputElement)) {
                    return;
                }

                checkbox.disabled = !isSpecific;

                if (!isSpecific) {
                    checkbox.checked = false;
                }
            });
        };

        toggles.forEach((toggle) => {
            toggle.addEventListener('change', syncAssociationMode);
        });

        syncAssociationMode();
    });
</script>
