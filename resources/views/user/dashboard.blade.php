<x-layouts.user>
    <section class="flex min-h-full w-full flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 xl:grid xl:min-h-full xl:grid-cols-3">
            <div class="order-2 col-span-2 flex flex-col gap-4 xl:order-1">
                <x-user.courses-stats :stats-url="route('user.dashboard.courses-stats')" />
                <x-user.job-based-requirements-card
                    :course-enrollments="$requirementCourseEnrollments"
                    :requirements="$unmetJobBasedRequirements"
                />
                <x-user.courses-list :courses="$recentCourses" />
            </div>
            <div class="order-1 flex flex-col gap-4 xl:order-2">
                <x-user.event-calendar />
            </div>
        </div>
    </section>
</x-layouts.user>
