{{-- Componente per modulo video --}}
<template id="tpl-video">
    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-4">
            <div id="video-loading" class="flex items-center justify-center py-12">
                <span class="loading loading-spinner loading-lg"></span>
            </div>
            <div id="video-player-wrapper" class="hidden">
                <div data-mux-player-container></div>
            </div>
            <div id="video-error" class="hidden text-error text-sm">
                {{ __('Impossibile caricare il video. Riprova più tardi.') }}
            </div>
            <div id="video-completed-msg" class="hidden">
                <div class="alert alert-success">
                    <x-lucide-check-circle class="h-5 w-5" />
                    <span>{{ __('Modulo completato!') }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

{{-- Script Mux player --}}
<script type="module" src="https://unpkg.com/@mux/mux-player@latest/dist/mux-player.js"></script>

{{-- Script specifico per il modulo video --}}
@vite('resources/js/modules/module-video.js')
