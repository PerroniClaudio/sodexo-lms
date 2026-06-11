@props([
    'course',
    'courseBaseValues',
    'courseValidator',
    'updateUrl',
])

<form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex items-start gap-4">
                <div class="flex-1">
                    <h2 class="card-title">{{ __('Durata corso') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Gestisci date e durata del corso.') }}
                    </p>
                </div>
            </div>

            @csrf
            @method('PUT')

            <div class="grid gap-6 md:grid-cols-2">
                <div class="form-control flex flex-col gap-2">
                    <label for="course_start_date" class="label p-0">
                        <span class="label-text font-medium">{{ __('Inizio corso') }}</span>
                    </label>
                    <input
                        id="course_start_date"
                        name="course_start_date"
                        type="date"
                        value="{{ $courseBaseValues['course_start_date'] }}"
                        class="input input-bordered w-full @error('course_start_date') input-error @enderror"
                    >
                    @error('course_start_date')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="course_end_date" class="label p-0">
                        <span class="label-text font-medium">{{ __('Fine corso') }}</span>
                    </label>
                    <input
                        id="course_end_date"
                        name="course_end_date"
                        type="date"
                        value="{{ $courseBaseValues['course_end_date'] }}"
                        class="input input-bordered w-full @error('course_end_date') input-error @enderror"
                    >
                    @error('course_end_date')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="access_closure_date" class="label p-0">
                        <span class="label-text font-medium">{{ __('Chiusura fruizione') }}</span>
                    </label>
                    <input
                        id="access_closure_date"
                        name="access_closure_date"
                        type="date"
                        value="{{ $courseBaseValues['access_closure_date'] }}"
                        class="input input-bordered w-full @error('access_closure_date') input-error @enderror"
                    >
                    @error('access_closure_date')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="expiry_date" class="label p-0">
                        <span class="label-text font-medium">{{ __('Data scadenza') }}</span>
                    </label>
                    <input
                        id="expiry_date"
                        name="expiry_date"
                        type="date"
                        value="{{ $courseBaseValues['expiry_date'] }}"
                        class="input input-bordered w-full @error('expiry_date') input-error @enderror"
                        required
                    >
                    @error('expiry_date')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="course_duration_hours" class="label p-0">
                        <span class="label-text font-medium">{{ __('Durata corso (ore)') }}</span>
                    </label>
                    <input
                        id="course_duration_hours"
                        name="course_duration_hours"
                        type="number"
                        min="0"
                        value="{{ $courseBaseValues['course_duration_hours'] }}"
                        class="input input-bordered w-full @error('course_duration_hours') input-error @enderror"
                    >
                    @error('course_duration_hours')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="interaction_duration_minutes" class="label p-0">
                        <span class="label-text font-medium">{{ __('Durata interattività (minuti)') }}</span>
                    </label>
                    <input
                        id="interaction_duration_minutes"
                        name="interaction_duration_minutes"
                        type="number"
                        min="0"
                        value="{{ $courseBaseValues['interaction_duration_minutes'] }}"
                        class="input input-bordered w-full @error('interaction_duration_minutes') input-error @enderror"
                    >
                    @error('interaction_duration_minutes')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">
                    <span>{{ __('Salva dati') }}</span>
                    <x-lucide-save class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</form>