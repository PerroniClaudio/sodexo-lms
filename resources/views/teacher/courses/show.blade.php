<x-layouts.user>
    @include('user.courses.partials.staff-course-content', ['routePrefix' => 'teacher'])

    @vite('resources/js/pages/staff-course-show.js')
</x-layouts.user>
