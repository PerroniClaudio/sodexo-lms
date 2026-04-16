<div class="form-control flex flex-col gap-2">
    <label for="status" class="label p-0">
        <span class="label-text font-medium">{{ __('Status') }}</span>
    </label>
    <select
        id="status"
        name="status"
        class="select select-bordered w-full @error('status') select-error @enderror"
        required
    >
        @foreach ($moduleStatusLabels as $moduleStatus => $moduleStatusLabel)
            <option value="{{ $moduleStatus }}" @selected(old('status', $module->status) === $moduleStatus)>
                {{ $moduleStatusLabel }}
            </option>
        @endforeach
    </select>
    @error('status')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>
