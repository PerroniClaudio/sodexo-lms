<x-layouts.user>
    <section class="flex min-h-full w-full flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 xl:grid xl:min-h-full xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-user.event-calendar :events-url="request()->boolean('test') ? route('tutor.dashboard.calendar-events.fake') : route('tutor.dashboard.calendar-events')" />
            </div>
        </div>
    </section>
</x-layouts.user>
