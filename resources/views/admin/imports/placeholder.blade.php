<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="$title" />

        <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-8 text-center shadow-sm">
            <div class="mx-auto flex max-w-xl flex-col gap-3">
                <span class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-full bg-base-200">
                    <x-lucide-file-up class="h-7 w-7 text-base-content/70" />
                </span>

                <h2 class="text-lg font-semibold">{{ $title }}</h2>
                <p class="text-sm text-base-content/70">{{ $description }}</p>
                <p class="text-sm text-base-content/50">{{ __('Pagina vuota temporanea.') }}</p>
            </div>
        </div>
    </div>
</x-layouts.admin>
