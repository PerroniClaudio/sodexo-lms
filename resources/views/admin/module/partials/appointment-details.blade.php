<div class="grid gap-6 md:grid-cols-3">
    <div class="form-control flex flex-col gap-2">
        <label for="appointment_date" class="label p-0">
            <span class="label-text font-medium">{{ __('Day') }}</span>
        </label>
        <input
            id="appointment_date"
            name="appointment_date"
            type="date"
            value="{{ old('appointment_date', $module->appointment_date?->format('Y-m-d')) }}"
            class="input input-bordered w-full @error('appointment_date') input-error @enderror"
            required
        >
        @error('appointment_date')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-control flex flex-col gap-2">
        <label for="appointment_start_time" class="label p-0">
            <span class="label-text font-medium">{{ __('Start time') }}</span>
        </label>
        <input
            id="appointment_start_time"
            name="appointment_start_time"
            type="time"
            value="{{ old('appointment_start_time', $module->appointment_start_time?->format('H:i')) }}"
            class="input input-bordered w-full @error('appointment_start_time') input-error @enderror"
            required
        >
        @error('appointment_start_time')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="form-control flex flex-col gap-2">
        <label for="appointment_end_time" class="label p-0">
            <span class="label-text font-medium">{{ __('End time') }}</span>
        </label>
        <input
            id="appointment_end_time"
            name="appointment_end_time"
            type="time"
            value="{{ old('appointment_end_time', $module->appointment_end_time?->format('H:i')) }}"
            class="input input-bordered w-full @error('appointment_end_time') input-error @enderror"
            required
        >
        @error('appointment_end_time')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>
</div>
