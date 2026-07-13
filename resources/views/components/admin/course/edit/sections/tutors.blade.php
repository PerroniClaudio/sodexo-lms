@props([
    'course',
    'courseValidator',
])

<section class="flex flex-col gap-6">
    <x-admin.course.edit-badge-bar :data="get_defined_vars()" />
    <x-admin.course.tutor-assignments-card :data="get_defined_vars()" />
</section>
