@props([
    'course',
    'courseBaseValues',
    'courseValidator',
    'jobUnits',
    'updateUrl',
    'venues',
])

@php
    $selectedMode = old('venue_mode', $course->job_unit_id ? 'job_unit' : 'venue');
    $jobUnitOptions = $jobUnits
        ->map(fn ($jobUnit): array => [
            'value' => (string) $jobUnit->getKey(),
            'label' => $jobUnit->name,
            'search' => collect([$jobUnit->name, $jobUnit->unit_code, $jobUnit->city?->name])->filter()->implode(' '),
            'description' => $jobUnit->full_address,
        ])
        ->values()
        ->all();
    $venueOptions = $venues
        ->map(fn ($venue): array => [
            'value' => (string) $venue->getKey(),
            'label' => $venue->name,
            'search' => collect([$venue->name, $venue->city?->name, $venue->address])->filter()->implode(' '),
            'description' => collect([$venue->address, $venue->postal_code, $venue->city?->name, $venue->province?->name])->filter()->implode(', '),
        ])
        ->values()
        ->all();
@endphp

<div class="flex min-w-0 flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card min-w-0 border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body min-w-0 gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex min-w-0 items-start gap-4">
                <div class="min-w-0 flex-1">
                    <h2 class="card-title">{{ __('Sede') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Seleziona una unità produttiva o crea una sede riutilizzabile.') }}
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ $updateUrl }}" class="flex min-w-0 flex-col gap-6" data-course-venue-form>
                @csrf
                @method('PUT')
                <input type="hidden" name="update_section" value="venue">

                @foreach (['title', 'code', 'description', 'year', 'status'] as $field)
                    <input type="hidden" name="{{ $field }}" value="{{ $courseBaseValues[$field] }}">
                @endforeach
                <input type="hidden" name="is_financed" value="{{ $courseBaseValues['is_financed'] ? '1' : '0' }}">
                @if ($courseBaseValues['funding_entity_id'])
                    <input type="hidden" name="funding_entity_id" value="{{ $courseBaseValues['funding_entity_id'] }}">
                @endif
                @if ($courseBaseValues['participant_presence_verification'])
                    <input type="hidden" name="participant_presence_verification" value="{{ $courseBaseValues['participant_presence_verification'] }}">
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-center gap-3 rounded-box border border-base-300 p-4">
                        <input
                            type="radio"
                            name="venue_mode"
                            value="job_unit"
                            class="radio radio-primary"
                            data-course-venue-mode
                            @checked($selectedMode === 'job_unit')
                        >
                        <span class="font-medium">{{ __('Unità produttiva esistente') }}</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-3 rounded-box border border-base-300 p-4">
                        <input
                            type="radio"
                            name="venue_mode"
                            value="venue"
                            class="radio radio-primary"
                            data-course-venue-mode
                            @checked($selectedMode !== 'job_unit')
                        >
                        <span class="font-medium">{{ __('Sede') }}</span>
                    </label>
                </div>
                @error('venue_mode')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror

                <div data-course-venue-panel="job_unit">
                    <x-searchable-select
                        name="job_unit_id"
                        id="job_unit_id"
                        :required="false"
                        :selected-value="$courseBaseValues['job_unit_id']"
                        :options="$jobUnitOptions"
                        :label="__('Unità produttiva')"
                        :placeholder="__('Cerca o seleziona una unità produttiva...')"
                    />
                </div>

                <div class="flex min-w-0 flex-col gap-5" data-course-venue-panel="venue">
                    <x-searchable-select
                        name="venue_id"
                        id="venue_id"
                        :required="false"
                        :selected-value="$courseBaseValues['venue_id']"
                        :options="$venueOptions"
                        :label="__('Sede esistente')"
                        :placeholder="__('Cerca una sede già salvata...')"
                    />

                    <div class="divider my-0">{{ __('oppure crea nuova sede') }}</div>

                    <div class="form-control flex flex-col gap-2">
                        <label for="venue_name" class="label p-0">
                            <span class="label-text font-medium">{{ __('Nome sede') }}</span>
                        </label>
                        <input
                            id="venue_name"
                            name="venue_name"
                            type="text"
                            value="{{ old('venue_name') }}"
                            class="input input-bordered w-full @error('venue_name') input-error @enderror"
                            placeholder="{{ __('Es. Palazzo della regione') }}"
                        >
                        @error('venue_name')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-address-selector-simple
                        :countryValue="old('country', 'it')"
                        :regionValue="old('region')"
                        :provinceValue="old('province')"
                        :cityValue="old('city')"
                        :addressValue="old('address')"
                        :postalCodeValue="old('postal_code')"
                        :required="false"
                    />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        {{ __('Salva sede') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
