<div class="form-control flex flex-col gap-2">
    <label class="label p-0">
        <span class="label-text font-medium">{{ __('Module title') }}</span>
    </label>
    <input
        type="text"
        value="{{ $module->title }}"
        class="input input-bordered w-full"
        disabled
    >
</div>
