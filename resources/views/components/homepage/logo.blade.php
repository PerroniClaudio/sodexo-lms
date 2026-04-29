@props([
    'appName' => config('app.name', 'Laravel'),
    'compact' => false,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 text-base-content']) }}>
    <div class="relative flex h-10 w-7 items-center justify-center">
        <x-lucide-dna class="h-10 text-primary" />
    </div>

    <div class="flex items-baseline gap-1 leading-none">
        <span class="text-xl font-extrabold tracking-normal sm:text-2xl">{{ $appName }}</span>
        @unless ($compact)
            <span class="-ml-0.5 self-start text-[10px] font-semibold text-base-content/60">®</span>
        @endunless
    </div>
</div>
