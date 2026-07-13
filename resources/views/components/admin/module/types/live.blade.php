@props(['data' => []])

@php
    extract($data);
@endphp

<div>
    <!-- It is quality rather than quantity that matters. - Lucius Annaeus Seneca -->
</div>
<x-admin.module.validity-badge :data="get_defined_vars()" />
<x-admin.module.editable-title :data="get_defined_vars()" />
<x-admin.module.description :data="get_defined_vars()" />
<x-admin.module.status :data="get_defined_vars()" />
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
<x-admin.module.appointment-details :data="get_defined_vars()" />
