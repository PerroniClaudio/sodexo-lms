<x-layouts.app>
    @vite('resources/js/livestream-tutor.js')

    <section class="min-h-screen bg-base-200" data-live-stream-root>
        <script type="application/json" data-live-stream-config>@json($liveStreamConfig)</script>

        <div class="mx-auto grid max-w-7xl gap-6 p-4 lg:p-6 xl:grid-cols-[minmax(0,2fr)_24rem]">
            <div class="space-y-6">
                <div class="rounded-box border border-base-300 bg-base-100 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <span class="badge badge-outline" data-live-stream-status-badge>{{ __('In attesa') }}</span>
                            <h1 class="mt-3 text-3xl font-semibold">{{ $module->title }}</h1>
                            <p class="mt-2 text-sm text-base-content/70" data-live-stream-message>
                                {{ __('Verifica i dispositivi e collegati alla diretta in modalità osservatore.') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn btn-primary" data-live-stream-join-button>
                                {{ __('Entra nella diretta') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-box border border-base-300 bg-neutral shadow-sm"
                    data-live-stream-main-stage
                >
                    <div class="flex min-h-[24rem] items-center justify-center px-6 py-12 text-center text-neutral-content/70">
                        {{ __('Il feed del docente comparirà qui dopo l’ingresso nella room.') }}
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5" data-live-stream-strip></div>

                <div class="rounded-box border border-base-300 bg-base-100 p-6 shadow-sm">
                    <h2 class="text-xl font-semibold">{{ __('Dettagli live') }}</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ __('Corso') }}</p>
                            <p class="mt-2 text-sm font-medium">{{ $course?->title ?? __('Corso non disponibile') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ __('Programmazione') }}</p>
                            <p class="mt-2 text-sm font-medium">
                                {{ $module->appointment_start_time?->format('d/m/Y H:i') }}
                                @if ($module->appointment_end_time !== null)
                                    {{ __('-') }} {{ $module->appointment_end_time->format('H:i') }}
                                @endif
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 max-w-3xl text-sm leading-6 text-base-content/70">
                        {{ $module->description ?: __('Nessuna descrizione disponibile per questo modulo live.') }}
                    </p>
                </div>
            </div>

            <aside class="space-y-6">
                <div class="rounded-box border border-base-300 bg-base-100 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold">{{ __('Dispositivi') }}</h2>
                            <p class="text-sm text-base-content/60">{{ __('L’anteprima serve solo per il controllo locale.') }}</p>
                        </div>

                        <button type="button" class="btn btn-ghost btn-sm" data-live-stream-preview-toggle>
                            {{ __('Anteprima') }}
                        </button>
                    </div>

                    <div class="mt-4 hidden space-y-4" data-live-stream-preview-panel>
                        <div class="overflow-hidden rounded-box border border-base-300 bg-neutral">
                            <video
                                class="aspect-video w-full bg-neutral object-cover"
                                autoplay
                                muted
                                playsinline
                                data-live-stream-preview
                            ></video>
                            <div
                                class="hidden aspect-video w-full items-center justify-center bg-neutral px-6 text-center text-sm text-neutral-content/70"
                                data-live-stream-preview-empty
                            >
                                {{ __('Anteprima video non disponibile') }}
                            </div>
                        </div>

                        <div class="rounded-box border border-base-300 bg-base-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium">{{ __('Microfono') }}</p>
                                <span class="text-xs text-base-content/60" data-live-stream-mic-label>{{ __('In attesa') }}</span>
                            </div>
                            <progress class="progress progress-primary mt-3 w-full" value="0" max="100" data-live-stream-mic-meter></progress>
                        </div>

                        <p class="text-sm text-base-content/60" data-live-stream-device-status>
                            {{ __('Consenti l’accesso a videocamera e microfono per visualizzare l’anteprima.') }}
                        </p>
                    </div>
                </div>

                <div class="rounded-box border border-base-300 bg-base-100 p-5 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold">{{ __('Discenti connessi') }}</h2>
                        <span class="text-sm text-base-content/60" data-live-stream-participant-count>0</span>
                    </div>
                    <div class="mt-4 space-y-3" data-live-stream-participant-list></div>
                </div>

                <div class="rounded-box border border-base-300 bg-base-100 p-5 shadow-sm">
                    <h2 class="text-lg font-semibold">{{ __('Chat') }}</h2>
                    <div class="mt-4 space-y-3" data-live-stream-chat-messages></div>
                </div>
            </aside>
        </div>

        <div class="hidden" data-live-stream-audio-stage></div>

        <template data-live-stream-chat-template>
            <article class="flex gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-base-200 text-xs font-semibold text-base-content" data-chat-initials></div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate text-sm font-medium" data-chat-author></p>
                        <span class="text-xs text-base-content/50" data-chat-time></span>
                    </div>
                    <div class="mt-2 rounded-box bg-base-200 px-3 py-2 text-sm" data-chat-bubble>
                        <p data-chat-body></p>
                    </div>
                </div>
            </article>
        </template>
    </section>
</x-layouts.app>
