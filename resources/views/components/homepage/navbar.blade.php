@props([
    'logoUrl' => null,
])

<header class="bg-base-200">
    <nav class="mx-auto flex h-[94px] w-full max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-0">
        <a href="{{ url('/') }}" aria-label="{{ __('Homepage') }}">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ __('Logo') }}" class="max-h-14 max-w-56 object-contain">
            @else
                <x-homepage.logo />
            @endif
        </a>

        <a
            href="{{ route('login') }}"
            class="inline-flex items-center rounded bg-primary px-4 py-2 text-sm font-semibold text-primary-content shadow-sm transition hover:bg-primary/90 focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:outline-none"
        >
            {{ __('Accedi') }}
        </a>
    </nav>
</header>
