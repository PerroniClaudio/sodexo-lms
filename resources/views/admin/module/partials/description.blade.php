<div class="form-control flex flex-col gap-2">
    <label for="description" class="label p-0">
        <span class="label-text font-medium">{{ __('Descrizione') }}</span>
    </label>
    <textarea
        id="description"
        name="description"
        class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
    >{{ old('description', $module->description) }}</textarea>
    @error('description')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>
