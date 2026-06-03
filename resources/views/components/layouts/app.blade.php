<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ config('app.theme') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Styles / Scripts -->
        
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
    </head>

    <body class="antialiased overflow-y-scroll [scrollbar-gutter:stable]">
        <div class="min-h-screen bg-base-100">
            {{ $slot }}
        </div>

        @stack('scripts')
            <div id="flash-stack" class="pointer-events-none fixed right-4 bottom-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2 sm:right-6 sm:bottom-6 sm:w-full sm:max-w-sm">
                @session('status')
                    <div class="alert alert-success pointer-events-auto flex w-full items-center gap-3 pr-3 shadow-lg" data-flash-item>
                        <x-lucide-circle-check class="h-5 w-5 shrink-0" />
                        <span class="flex-1">{{ __($value) }}</span>
                        <button
                            type="button"
                            class="close-btn cursor-pointer transition-transform hover:scale-110"
                            aria-label="{{ __('Chiudi notifica') }}"
                        >
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>
                @endsession

                @session('error')
                    <div class="alert alert-error pointer-events-auto flex w-full items-center gap-3 pr-3 shadow-lg" data-flash-item>
                        <x-lucide-circle-alert class="h-5 w-5 shrink-0" />
                        <span class="flex-1">{{ __($value) }}</span>
                        <button
                            type="button"
                            class="close-btn cursor-pointer transition-transform hover:scale-110"
                            aria-label="{{ __('Chiudi notifica') }}"
                        >
                            <x-lucide-x class="h-4 w-4" />
                        </button>
                    </div>
                @endsession
            </div>

            <template id="flash-template-success">
                <div class="alert alert-success pointer-events-auto flex w-full items-center gap-3 pr-3 shadow-lg" data-flash-item>
                    <x-lucide-circle-check class="h-5 w-5 shrink-0" />
                    <span class="flex-1"></span>
                    <button type="button" class="close-btn cursor-pointer transition-transform hover:scale-110" aria-label="{{ __('flash.close_notification') }}">
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </div>
            </template>
            <template id="flash-template-error">
                <div class="alert alert-error pointer-events-auto flex w-full items-center gap-3 pr-3 shadow-lg" data-flash-item>
                    <x-lucide-circle-alert class="h-5 w-5 shrink-0" />
                    <span class="flex-1"></span>
                    <button type="button" class="close-btn cursor-pointer transition-transform hover:scale-110" aria-label="{{ __('flash.close_notification') }}">
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </div>
            </template>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var flashStack = document.getElementById('flash-stack');
                    var flashDuration = 3000;

                    if (!flashStack) {
                        return;
                    }

                    var dismissFlash = function (alert) {
                        if (!alert || !alert.isConnected) {
                            return;
                        }

                        alert.remove();
                    };

                    var clearDismissTimer = function (alert) {
                        if (alert.dataset.dismissTimeoutId) {
                            window.clearTimeout(Number(alert.dataset.dismissTimeoutId));
                            delete alert.dataset.dismissTimeoutId;
                        }
                    };

                    var scheduleDismiss = function (alert, delay) {
                        clearDismissTimer(alert);

                        var remaining = typeof delay === 'number' ? delay : flashDuration;
                        alert.dataset.dismissRemaining = String(remaining);
                        alert.dataset.dismissStartedAt = String(Date.now());
                        alert.dataset.dismissTimeoutId = String(window.setTimeout(function () {
                            dismissFlash(alert);
                        }, remaining));
                    };

                    var pauseDismiss = function (alert) {
                        if (!alert || !alert.isConnected) {
                            return;
                        }

                        var startedAt = Number(alert.dataset.dismissStartedAt || Date.now());
                        var remaining = Number(alert.dataset.dismissRemaining || flashDuration);
                        var elapsed = Date.now() - startedAt;
                        var nextRemaining = Math.max(0, remaining - elapsed);

                        clearDismissTimer(alert);
                        alert.dataset.dismissRemaining = String(nextRemaining);
                    };

                    var resumeDismiss = function (alert) {
                        if (!alert || !alert.isConnected) {
                            return;
                        }

                        scheduleDismiss(alert, Number(alert.dataset.dismissRemaining || flashDuration));
                    };

                    var bindFlashInteractions = function (alert) {
                        if (!alert || alert.dataset.dismissBound === 'true') {
                            return;
                        }

                        alert.dataset.dismissBound = 'true';
                        alert.addEventListener('mouseenter', function () {
                            pauseDismiss(alert);
                        });
                        alert.addEventListener('mouseleave', function () {
                            resumeDismiss(alert);
                        });
                        alert.addEventListener('focusin', function () {
                            pauseDismiss(alert);
                        });
                        alert.addEventListener('focusout', function (event) {
                            if (alert.contains(event.relatedTarget)) {
                                return;
                            }

                            resumeDismiss(alert);
                        });
                    };

                    flashStack.querySelectorAll('[data-flash-item]').forEach(function (alert) {
                        bindFlashInteractions(alert);
                        scheduleDismiss(alert);
                    });

                    flashStack.addEventListener('click', function (event) {
                        if (!event.target.closest('.close-btn')) {
                            return;
                        }

                        dismissFlash(event.target.closest('[data-flash-item]'));
                    });

                    window.showFlash = function(type, message) {
                        var tplId = type === 'error' ? 'flash-template-error' : 'flash-template-success';
                        var tpl = document.getElementById(tplId);
                        if (!tpl) {
                            return;
                        }

                        var clone = tpl.content.cloneNode(true);
                        var span = clone.querySelector('span');
                        if (span) {
                            span.textContent = message;
                        }

                        var alert = clone.querySelector('.alert');
                        if (alert) {
                            bindFlashInteractions(alert);
                            flashStack.appendChild(alert);
                            scheduleDismiss(alert);
                        }
                    };

                    @if ($errors->any())
                        window.showFlash(
                            'error',
                            @json($errors->all()[0].(count($errors->all()) > 1 ? ' (+' . (count($errors->all()) - 1) . ' altri errori)' : ''))
                        );
                    @endif
                });
            </script>
    </body>
</html>
