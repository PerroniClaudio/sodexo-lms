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

@if ($module->teachingMaterials->isNotEmpty())
    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-4">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Materiale didattico') }}</h2>
            </div>

            <div class="grid gap-3">
                @foreach ($module->teachingMaterials as $material)
                    <div class="flex flex-col gap-3 rounded border border-base-300 p-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $material->original_name }}</p>
                            <p class="text-sm text-base-content/60">
                                {{ $material->mime_type ?: __('File') }} · {{ Illuminate\Support\Number::fileSize($material->size_bytes) }}
                            </p>
                        </div>
                        <a href="{{ route('user.courses.modules.video.teaching-materials.download', [$course, $module, $material]) }}" class="btn btn-outline btn-primary btn-sm">
                            <x-lucide-download class="h-4 w-4" />
                            <span>{{ __('Scarica') }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

{{-- Script Mux player --}}
<script type="module" src="https://unpkg.com/@mux/mux-player@latest/dist/mux-player.js"></script>

{{-- Script specifico per il modulo video --}}
@vite('resources/js/modules/module-video.js')
