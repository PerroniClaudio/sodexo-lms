@props([
    'course',
    'courseBaseValues',
    'courseDetailAccordionFields',
    'courseStatusLabels',
    'courseValidator',
    'updateUrl',
])

<form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <h2 class="card-title">{{ __('Dati anagrafici corso') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Gestisci le informazioni principali del corso.') }}
                    </p>
                </div>
            </div>

            @csrf
            @method('PUT')

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
                        required
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
                        required
                    >
                    @error('code')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2 md:col-span-2">
                    <label for="description" class="label p-0">
                        <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
                        required
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
                        required
                    >
                    @error('year')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
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
                        required
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

    <div class="flex flex-col gap-3">
        @foreach ($courseDetailAccordionFields as $fieldKey => $fieldLabel)
            <div class="collapse collapse-arrow border border-base-300 bg-base-100 shadow-sm">
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