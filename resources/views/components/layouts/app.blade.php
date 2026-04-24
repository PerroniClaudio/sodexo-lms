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

            @session('error')
                <div class="pointer-events-none fixed right-4 bottom-4 z-50 sm:right-6 sm:bottom-6">
                    <input id="flash-error-dismiss" type="checkbox" class="peer sr-only">

                    <div class="alert alert-error pointer-events-auto flex w-full max-w-sm items-center gap-3 pr-3 shadow-lg peer-checked:hidden">
                        <x-lucide-circle-alert class="h-5 w-5 shrink-0" />
                        <span class="flex-1">{{ $value }}</span>

                        <label
                            for="flash-error-dismiss"
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

        @stack('scripts')
            <!-- Template per alert dinamici -->
            <div id="dynamic-flash-container" class="pointer-events-none fixed right-4 bottom-4 z-50 sm:right-6 sm:bottom-6 flex flex-col gap-2"></div>

            <template id="flash-template-success">
                <div class="alert alert-success pointer-events-auto flex w-full max-w-sm items-center gap-3 pr-3 shadow-lg">
                    <x-lucide-circle-check class="h-5 w-5 shrink-0" />
                    <span class="flex-1"></span>
                    <button type="button" class="close-btn cursor-pointer transition-transform hover:scale-110" aria-label="Chiudi notifica">
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </div>
            </template>
            <template id="flash-template-error">
                <div class="alert alert-error pointer-events-auto flex w-full max-w-sm items-center gap-3 pr-3 shadow-lg">
                    <x-lucide-circle-alert class="h-5 w-5 shrink-0" />
                    <span class="flex-1"></span>
                    <button type="button" class="close-btn cursor-pointer transition-transform hover:scale-110" aria-label="Chiudi notifica">
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </div>
            </template>
            <script>
                // Chiude automaticamente le notifiche flash dopo 3 secondi
                document.addEventListener('DOMContentLoaded', function () {
                    setTimeout(function () {
                        var status = document.getElementById('flash-status-dismiss');
                        if (status && !status.checked) status.checked = true;
                        var error = document.getElementById('flash-error-dismiss');
                        if (error && !error.checked) error.checked = true;
                    }, 3000);
                        // Gestione chiusura manuale per alert dinamici
                        document.getElementById('dynamic-flash-container').addEventListener('click', function(e) {
                            if (e.target.closest('.close-btn')) {
                                const alert = e.target.closest('.alert');
                                if (alert) alert.remove();
                            }
                        });
                });

                    // Funzione globale per mostrare alert dinamici
                    window.showFlash = function(type, message) {
                        var tplId = type === 'error' ? 'flash-template-error' : 'flash-template-success';
                        var tpl = document.getElementById(tplId);
                        if (!tpl) return;
                        var clone = tpl.content.cloneNode(true);
                        var span = clone.querySelector('span');
                        if (span) span.textContent = message;
                        var alert = clone.querySelector('.alert');
                        if (alert) {
                            document.getElementById('dynamic-flash-container').appendChild(alert);
                            setTimeout(function() {
                                alert.remove();
                            }, 3000);
                        }
                    }
            </script>
    </body>
</html>
