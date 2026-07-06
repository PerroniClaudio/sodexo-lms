@props([
    'course',
    'courseBaseValues',
    'courseDetailAccordionFields',
    'courseParticipantPresenceVerificationLabels',
    'courseStatusLabels',
    'courseValidator',
    'fundingEntities',
    'languageLevels',
    'updateUrl',
])

@php
    $defaultRequiredLanguageLevelId = (string) ($languageLevels->firstWhere('is_default', true)?->getKey() ?? $languageLevels->first()?->getKey() ?? '');
    $selectedRequiredLanguageLevelId = (string) ($courseBaseValues['required_language_level_id'] ?? '');

    if ($selectedRequiredLanguageLevelId === '') {
        $selectedRequiredLanguageLevelId = $defaultRequiredLanguageLevelId;
    }
@endphp

<div class="flex min-w-0 flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card min-w-0 border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body min-w-0 gap-6 p-4 sm:p-6 lg:p-8">
            <div class="flex min-w-0 items-start gap-4">
                <div class="min-w-0 flex-1">
                    <h2 class="card-title">{{ __('Dati anagrafici corso') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Gestisci le informazioni principali del corso.') }}
                    </p>
                </div>
            </div>

            <form method="POST" action="{{ $updateUrl }}" class="flex min-w-0 flex-col gap-6" data-course-details-form>
            @csrf
            @method('PUT')
            <input type="hidden" name="detach_from_unpublished_training_paths" value="0" data-detach-from-unpublished-training-paths>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="form-control flex flex-col gap-2 md:col-span-2">
                    <label for="title" class="label p-0">
                        <span class="label-text font-medium">{{ __('Titolo del corso') }}</span>
                    </label>
                    <input
                        id="title"
                        name="title"
                        type="text"
                        value="{{ $courseBaseValues['title'] }}"
                        class="input input-bordered w-full @error('title') input-error @enderror"
                    >
                    @error('title')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2 md:col-span-2">
                    <label for="code" class="label p-0">
                        <span class="label-text font-medium">{{ __('Codice corso') }}</span>
                    </label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        value="{{ $courseBaseValues['code'] }}"
                        class="input input-bordered w-full @error('code') input-error @enderror"
                    >
                    @error('code')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2 md:col-span-2">
                    <label for="description" class="label p-0">
                        <span class="label-text font-medium">{{ __('Informazioni sul corso') }}</span>
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
                    >{{ $courseBaseValues['description'] }}</textarea>
                    @error('description')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                @if (in_array($course->type, ['res', 'async', 'blended'], true))
                    <div class="form-control flex flex-col gap-2">
                        <label for="max_participants" class="label p-0">
                            <span class="label-text font-medium">{{ __('Numero massimo partecipanti') }}</span>
                        </label>
                        <input
                            id="max_participants"
                            name="max_participants"
                            type="number"
                            min="1"
                            value="{{ $courseBaseValues['max_participants'] }}"
                            class="input input-bordered w-full @error('max_participants') input-error @enderror"
                        >
                        @error('max_participants')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                @if (in_array($course->type, ['res', 'blended'], true))
                    <div class="form-control flex flex-col gap-2">
                        <label for="participant_presence_verification" class="label p-0">
                            <span class="label-text font-medium">{{ __('Verifica Presenza Partecipanti') }}</span>
                        </label>
                        <select
                            id="participant_presence_verification"
                            name="participant_presence_verification"
                            class="select select-bordered w-full @error('participant_presence_verification') select-error @enderror"
                        >
                            <option value="">{{ __('Seleziona modalità') }}</option>
                            @foreach ($courseParticipantPresenceVerificationLabels as $verification => $verificationLabel)
                                <option value="{{ $verification }}" @selected($courseBaseValues['participant_presence_verification'] === $verification)>
                                    {{ $verificationLabel }}
                                </option>
                            @endforeach
                        </select>
                        @error('participant_presence_verification')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                @endif

                <div class="form-control flex flex-col gap-2">
                    <label for="year" class="label p-0">
                        <span class="label-text font-medium">{{ __('Anno del corso') }}</span>
                    </label>
                    <input
                        id="year"
                        name="year"
                        type="number"
                        value="{{ $courseBaseValues['year'] }}"
                        class="input input-bordered w-full @error('year') input-error @enderror"
                        min="1900"
                        max="2100"
                    >
                    @error('year')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2 md:col-span-2">
                    <label class="label cursor-pointer justify-start gap-3 p-0">
                        <input
                            id="is_language_verification_course"
                            name="is_language_verification_course"
                            type="checkbox"
                            value="1"
                            class="checkbox checkbox-primary"
                            data-language-verification-toggle
                            @checked($courseBaseValues['is_language_verification_course'])
                        >
                        <span class="label-text font-medium">{{ __('È un corso per la verifica della conoscenza della lingua?') }}</span>
                    </label>
                    @error('is_language_verification_course')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2" data-required-language-level-wrapper>
                    <label for="required_language_level_id" class="label p-0">
                        <span class="label-text font-medium">{{ __('Livello lingua richiesto per accedere al corso') }}</span>
                    </label>
                    <select
                        id="required_language_level_id"
                        name="required_language_level_id"
                        data-default-language-level-id="{{ $defaultRequiredLanguageLevelId }}"
                        class="select select-bordered w-full @error('required_language_level_id') select-error @enderror"
                    >
                        @foreach ($languageLevels as $languageLevel)
                            <option value="{{ $languageLevel->id }}" @selected($selectedRequiredLanguageLevelId === (string) $languageLevel->id)>
                                {{ strtoupper($languageLevel->name) }}
                            </option>
                        @endforeach
                    </select>
                    @error('required_language_level_id')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2 md:col-span-2" data-granted-language-level-wrapper>
                    <label for="grants_language_level_id" class="label p-0">
                        <span class="label-text font-medium">{{ __('Livello verificato ottenibile al completamento') }}</span>
                    </label>
                    <select
                        id="grants_language_level_id"
                        name="grants_language_level_id"
                        class="select select-bordered w-full @error('grants_language_level_id') select-error @enderror"
                    >
                        <option value="">{{ __('Seleziona livello') }}</option>
                        @foreach ($languageLevels as $languageLevel)
                            <option value="{{ $languageLevel->id }}" @selected((string) $courseBaseValues['grants_language_level_id'] === (string) $languageLevel->id)>
                                {{ strtoupper($languageLevel->name) }}
                            </option>
                        @endforeach
                    </select>
                    @error('grants_language_level_id')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2 md:col-span-2">
                    <label class="label cursor-pointer justify-start gap-3 p-0">
                        <input
                            id="is_financed"
                            name="is_financed"
                            type="checkbox"
                            value="1"
                            class="checkbox checkbox-primary"
                            data-course-financed-toggle
                            @checked(old('is_financed', $courseBaseValues['is_financed'] ?? $course->is_financed))
                        >
                        <span class="label-text font-medium">{{ __('Corso finanziato') }}</span>
                    </label>
                    @error('is_financed')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                @php
                    $selectedFundingEntityId = old('funding_entity_id', $courseBaseValues['funding_entity_id'] ?? $course->funding_entity_id);
                    $fundingEntityOptions = $fundingEntities
                        ->map(fn ($fundingEntity): array => [
                            'value' => (string) $fundingEntity->getKey(),
                            'label' => $fundingEntity->company_name,
                            'search' => $fundingEntity->company_name,
                        ])
                        ->values()
                        ->all();
                @endphp

                <div class="md:col-span-2" data-funding-entity-wrapper>
                    <x-searchable-select
                        name="funding_entity_id"
                        id="funding_entity_id"
                        :required="false"
                        :selected-value="$selectedFundingEntityId"
                        :options="$fundingEntityOptions"
                        :label="__('Ente finanziatore')"
                        :placeholder="__('Cerca o seleziona un ente finanziatore...')"
                    />
                </div>

                @if ($course->edition > 1)
                    <div class="form-control flex flex-col gap-2">
                        <label for="edition" class="label p-0">
                            <span class="label-text font-medium">{{ __('Edizione') }}</span>
                        </label>
                        <input
                            id="edition"
                            type="number"
                            value="{{ $course->edition }}"
                            class="input input-bordered w-full"
                            readonly
                            disabled
                        >
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <span class="label p-0">
                            <span class="label-text font-medium">{{ __('Corso originale') }}</span>
                        </span>
                        <a
                            href="{{ route('admin.courses.edit', $course->original_course_id) }}"
                            class="btn btn-outline w-full justify-start"
                        >
                            <x-lucide-arrow-left class="h-4 w-4" />
                            <span>{{ __('Vai al corso originale') }}</span>
                        </a>
                    </div>
                @endif

                <div class="form-control flex flex-col gap-2 md:col-span-2">
                    <label for="status" class="label p-0">
                        <span class="label-text font-medium">{{ __('Stato') }}</span>
                    </label>
                    <select
                        id="status"
                        name="status"
                        class="select select-bordered w-full @error('status') select-error @enderror"
                    >
                        @foreach ($courseStatusLabels as $courseStatus => $courseStatusLabel)
                            <option value="{{ $courseStatus }}" @selected($courseBaseValues['status'] === $courseStatus)>
                                {{ $courseStatusLabel }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

        </div>
    </div>

    <div class="flex min-w-0 flex-col gap-3">
        @foreach ($courseDetailAccordionFields as $fieldKey => $fieldLabel)
            <div class="collapse collapse-arrow min-w-0 border border-base-300 bg-base-100 shadow-sm">
                <input type="radio" name="course-details-accordion" @checked($loop->first) />
                <div class="collapse-title text-base font-medium">
                    {{ $fieldLabel }}
                </div>
                <div class="collapse-content">
                    <div class="form-control flex flex-col gap-2 pt-1">
                        <textarea
                            id="{{ $fieldKey }}"
                            name="{{ $fieldKey }}"
                            class="textarea textarea-bordered min-h-32 w-full @error($fieldKey) textarea-error @enderror"
                        >{{ $courseBaseValues[$fieldKey] }}</textarea>
                        @error($fieldKey)
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">
            <span>{{ __('Salva dati') }}</span>
            <x-lucide-save class="h-4 w-4" />
        </button>
    </div>
            </form>
</div>

@push('scripts')
    <script>
        (() => {
            const financedToggle = document.querySelector('[data-course-financed-toggle]');
            const fundingEntityWrapper = document.querySelector('[data-funding-entity-wrapper]');
            const fundingEntityInput = fundingEntityWrapper?.querySelector('[data-input]');
            const fundingEntityHidden = fundingEntityWrapper?.querySelector('[data-hidden]');
            const verificationToggle = document.querySelector('[data-language-verification-toggle]');
            const requiredLevelWrapper = document.querySelector('[data-required-language-level-wrapper]');
            const requiredLevelSelect = document.querySelector('#required_language_level_id');
            const grantedLevelWrapper = document.querySelector('[data-granted-language-level-wrapper]');
            const grantedLevelSelect = document.querySelector('#grants_language_level_id');

            if (!financedToggle || !fundingEntityWrapper || !fundingEntityInput || !fundingEntityHidden) {
                return;
            }

            const syncFundingEntityVisibility = () => {
                const isVisible = financedToggle.checked;

                fundingEntityWrapper.classList.toggle('hidden', !isVisible);
                fundingEntityInput.disabled = !isVisible;

                if (!isVisible) {
                    fundingEntityInput.value = '';
                    fundingEntityHidden.value = '';
                    fundingEntityInput.setCustomValidity('');
                }
            };

            financedToggle.addEventListener('change', syncFundingEntityVisibility);
            syncFundingEntityVisibility();

            if (!verificationToggle || !requiredLevelWrapper || !requiredLevelSelect || !grantedLevelWrapper || !grantedLevelSelect) {
                return;
            }

            const defaultRequiredLanguageLevelId = requiredLevelSelect.dataset.defaultLanguageLevelId ?? '';
            let lastRequiredLanguageLevelId = requiredLevelSelect.value || defaultRequiredLanguageLevelId;

            const syncLanguageLevelVisibility = () => {
                const isVerificationCourse = verificationToggle.checked;

                requiredLevelWrapper.classList.toggle('hidden', isVerificationCourse);
                grantedLevelWrapper.classList.toggle('hidden', !isVerificationCourse);
                requiredLevelSelect.disabled = isVerificationCourse;

                if (isVerificationCourse && requiredLevelSelect.value !== '') {
                    lastRequiredLanguageLevelId = requiredLevelSelect.value;
                }

                if (!isVerificationCourse && requiredLevelSelect.value === '') {
                    requiredLevelSelect.value = lastRequiredLanguageLevelId || defaultRequiredLanguageLevelId;
                }

                if (!isVerificationCourse) {
                    grantedLevelSelect.value = '';
                }
            };

            verificationToggle.addEventListener('change', syncLanguageLevelVisibility);
            syncLanguageLevelVisibility();
        })();
    </script>
@endpush
