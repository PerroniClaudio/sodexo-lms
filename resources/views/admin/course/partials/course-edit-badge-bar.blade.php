<div class="flex flex-wrap items-start justify-end gap-2">
    <span class="badge badge-sm badge-outline min-h-7 min-w-28 justify-center px-2.5 text-[11px] font-medium whitespace-nowrap">
        {{ __('Tipologia: :type', ['type' => $courseTypeLabels[$course->type] ?? $course->type]) }}
    </span>
    @include('admin.course.partials.course-validity-badge')
</div>
