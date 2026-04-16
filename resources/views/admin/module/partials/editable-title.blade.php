<div class="form-control flex flex-col gap-2">
    <label for="title" class="label p-0">
        <span class="label-text font-medium">{{ __('Module title') }}</span>
    </label>
    <input
        id="title"
        name="title"
        type="text"
        value="{{ old('title', $module->title) }}"
        class="input input-bordered w-full @error('title') input-error @enderror"
        required
    >
    @error('title')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>
