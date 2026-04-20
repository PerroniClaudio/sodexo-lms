<x-layouts.app>
    <section class="min-h-screen bg-base-200">
        <div class="navbar border-b border-base-300 bg-base-100 px-4 shadow-sm sm:px-6 lg:px-8">
            <div class="flex-1">
                <a href="{{ url('/') }}" class="btn btn-ghost text-lg font-semibold">
                    {{ config('app.name', 'Laravel') }}
                </a>
            </div>

            <div class="flex-none">
                <a href="{{ route('login') }}" class="btn btn-primary">
                    {{ __('Login') }}
                </a>
            </div>
        </div>
    </section>
</x-layouts.app>
