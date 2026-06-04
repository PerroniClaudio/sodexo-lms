<x-layouts.user>
    <section class="flex min-h-full w-full flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <div class="flex flex-col gap-4 xl:grid xl:min-h-full xl:grid-cols-3 xl:items-stretch">
            <div class="col-span-2 flex">
                <x-teacher.event-calendar />
            </div>
            <div class="flex h-full">
                <x-teacher.next-events :events="$nextEvents" />
            </div>
            <div class="col-span-3">
                <x-teacher.your-courses />
            </div>
            <div class="col-span-2">
                <x-teacher.user-engagement />
            </div>
            <div class="flex">
                <x-teacher.user-activity />
            </div>       
        </div>
    </section>
</x-layouts.user>
