<div>
    <!-- It is quality rather than quantity that matters. - Lucius Annaeus Seneca -->
</div>
@include('admin.module.partials.editable-title')
@include('admin.module.partials.description')
@include('admin.module.partials.status')
<div class="form-control">
    <label for="is_live_teacher" class="label cursor-pointer justify-start gap-3">
        <input type="hidden" name="is_live_teacher" value="0">
        <input
            id="is_live_teacher"
            name="is_live_teacher"
            type="checkbox"
            value="1"
            class="checkbox @error('is_live_teacher') checkbox-error @enderror"
            @checked(old('is_live_teacher', $module->is_live_teacher))
        >
        <span class="label-text font-medium">{{ __('Live con docente') }}</span>
    </label>
    @error('is_live_teacher')
        <p class="text-sm text-error">{{ $message }}</p>
    @enderror
</div>
@include('admin.module.partials.appointment-details')
