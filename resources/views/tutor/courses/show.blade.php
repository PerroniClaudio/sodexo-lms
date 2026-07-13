<x-layouts.user>
    <x-user.courses.staff-course-content :data="array_merge(get_defined_vars(), ['routePrefix' => 'tutor'])" />

    @vite('resources/js/pages/staff-course-show.js')
</x-layouts.user>
