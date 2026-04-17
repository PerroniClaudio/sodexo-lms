<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ config('app.theme') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Styles / Scripts -->
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
    </head>

    <body class="antialiased">
        <div class="min-h-screen bg-base-100">
            @session('status')
                <div class="pointer-events-none fixed right-4 bottom-4 z-50 sm:right-6 sm:bottom-6">
                    <input id="flash-status-dismiss" type="checkbox" class="peer sr-only">

                    <div class="alert alert-success pointer-events-auto flex w-full max-w-sm items-center gap-3 pr-3 shadow-lg peer-checked:hidden">
                        <x-lucide-circle-check class="h-5 w-5 shrink-0" />
                        <span class="flex-1">{{ $value }}</span>

                        <label
                            for="flash-status-dismiss"
                            class="cursor-pointer transition-transform hover:scale-110"
                            aria-label="{{ __('Chiudi notifica') }}"
                        >
                            <x-lucide-x class="h-4 w-4" />
                        </label>
                    </div>
                </div>
            @endsession

            {{ $slot }}
        </div>
    </body>
</html>
