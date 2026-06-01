<x-layouts.user>
    <section class="flex w-full flex-col gap-6 p-4 sm:p-6 lg:p-8 h-screen">
        <div class="flex flex-col xl:grid xl:grid-cols-3 gap-4 h-full">
            <div class="col-span-2 flex flex-col gap-4">
                <x-user.courses-stats :stats-url="route('user.dashboard.courses-stats')" />
                <x-user.courses-list />
            </div>
            <div class="flex flex-col gap-4">
                <x-user.event-calendar />
            </div>
        </div>
    </section>
</x-layouts.user>
