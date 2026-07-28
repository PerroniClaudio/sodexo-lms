@props([
    'appName' => config('app.name', 'Laravel'),
    'logoUrl' => null,
])

<footer class="bg-base-200 px-4 py-8 text-base-content sm:px-6 lg:px-0">
    <div class="mx-auto flex w-full max-w-6xl flex-col items-start justify-between gap-4 border-t border-base-300 pt-6 sm:flex-row sm:items-center">
        <div class="flex items-center gap-3">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ __('Logo') }}" class="max-h-10 max-w-40 object-contain">
            @else
                <x-homepage.logo compact class="scale-90 origin-left" />
            @endif
            <span class="flex items-center gap-1 text-sm">
                <x-lucide-copyright class="h-4 w-4" />
                {{ now()->year }} {{ $appName }} - Labor Evolution SRL
            </span>
        </div>
{{-- 
        <div class="flex gap-4 text-sm font-semibold">
            <a href="{{ url('/cookie-policy') }}" class="hover:text-primary">Cookie</a>
            <a href="{{ url('/privacy-policy') }}" class="hover:text-primary">Privacy policy</a>
        </div> --}}
    </div>
</footer>
