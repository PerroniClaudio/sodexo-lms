@props([
    'course',
    'courseValidator',
])

<section class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')
    @include('admin.course.partials.tutor-assignments-card')
</section>
