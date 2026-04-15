@props([
    'title',
    'actionLabel' => null,
    'actionUrl' => null,
])

<div {{ $attributes->class(['flex flex-col gap-3']) }}>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex flex-col gap-1">
            <h1 class="text-3xl font-semibold text-base-content">{{ $title }}</h1>

            @if (trim($slot) !== '')
                <div class="text-sm text-base-content/70">
                    {{ $slot }}
                </div>
            @endif
        </div>

        @if ($actionLabel && $actionUrl)
            <div class="shrink-0">
                <a href="{{ $actionUrl }}" class="btn btn-primary">
                    <span>{{ $actionLabel }}</span>
                    <x-lucide-plus class="h-4 w-4" />
                </a>
            </div>
        @elseif (isset($actions))
            <div class="shrink-0">
                {{ $actions }}
            </div>
        @endif
    </div>

    <hr class="border-base-300">
</div>
