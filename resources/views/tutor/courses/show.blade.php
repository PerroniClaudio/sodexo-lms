<x-layouts.user>
    @include('user.courses.partials.staff-course-content', ['routePrefix' => 'tutor'])

    @vite('resources/js/pages/staff-course-show.js')
</x-layouts.user>
