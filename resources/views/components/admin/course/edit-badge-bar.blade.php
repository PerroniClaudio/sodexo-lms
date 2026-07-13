@props(['data' => []])

@php
    extract($data);
@endphp

@php
    $courseTypeLabel = \App\Models\Course::availableTypeLabels()[$course->type] ?? $course->type;
@endphp

<div class="flex flex-wrap items-start justify-end gap-2">
    <span class="badge badge-sm badge-outline min-h-7 min-w-28 justify-center px-2.5 text-[11px] font-medium whitespace-nowrap">
        {{ __('Tipologia: :type', ['type' => $courseTypeLabel]) }}
    </span>
    <x-admin.course.validity-badge :data="get_defined_vars()" />
</div>
