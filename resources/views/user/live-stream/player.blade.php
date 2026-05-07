<x-layouts.app>
    @vite('resources/js/livestream-user.js')

    @php($canRaiseHand = (bool) data_get($liveStreamConfig, 'capabilities.canRaiseHand', false))
    @php($canModerateChat = (bool) data_get($liveStreamConfig, 'capabilities.canModerateChat', false))

    <section class="min-h-screen w-full bg-base-100" data-live-stream-root>
        <script type="application/json" data-live-stream-config>@json($liveStreamConfig)</script>

        <div class="grid min-h-screen xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="min-h-0">
                <div
                    class="h-full min-h-0 overflow-y-auto bg-base-200 xl:border-r xl:border-base-300"
                    data-livestream-user-main-scroll
                >
                    <div class="px-4 py-4 sm:px-6 sm:py-6 xl:px-8 xl:py-8">
                        <div class="mx-auto flex w-full max-w-6xl flex-col gap-5">
                            <div class="relative" data-live-stream-main-stage-shell>
                                <div
                                    class="aspect-video w-full overflow-hidden rounded-[1.75rem]"
                                    data-live-stream-main-stage
                                >
                                    <div class="flex h-full w-full items-center justify-center rounded-[1.75rem] bg-[#24285f] px-6 py-12 text-center text-white">
                                        <div class="space-y-2">
                                            <p class="text-sm font-semibold">{{ __('Docente non connesso') }}</p>
                                            <p class="text-xs text-white/70">{{ __('Il feed apparirà qui appena il docente entra in diretta') }}</p>
                                        </div>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="btn btn-sm absolute right-4 top-4 z-10 hidden border-white/20 bg-black/55 text-white shadow-lg backdrop-blur hover:border-white/30 hover:bg-black/70"
                                    data-live-stream-fullscreen-toggle
                                    aria-label="{{ __('Apri feed docente a schermo intero') }}"
                                    title="{{ __('Apri feed docente a schermo intero') }}"
                                >
                                    <span class="sr-only" data-live-stream-fullscreen-label>{{ __('Schermo intero') }}</span>
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between gap-3 px-1">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">{{ __('Telecamere live') }}</p>
                                        <p class="mt-1 text-sm text-base-content/60">{{ __('I partecipanti collegati compaiono qui sotto.') }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        class="flex items-center justify-center rounded-full border border-base-300 px-3 py-2 text-base-content/60 transition hover:border-base-content/20 hover:text-base-content"
                                        aria-label="{{ __('Vai ai dettagli live') }}"
                                        data-livestream-user-scroll-details
                                    >
                                        <x-lucide-chevron-down class="h-4 w-4" />
                                    </button>
                                </div>

                                <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" data-live-stream-strip></div>
                            </div>
                        </div>
                    </div>

                    <div class="mx-auto w-full max-w-6xl px-4 pb-6 sm:px-6 xl:px-8 xl:pb-8" data-livestream-user-details-section>
                        <div class="tabs tabs-lift">
                            <input
                                type="radio"
                                name="live-stream-tabs"
                                class="tab"
                                aria-label="{{ __('Dettagli live') }}"
                                checked="checked"
                            />
                            <div class="tab-content border-base-300 bg-base-100 p-6">
                                <div>
                                    <div class="grid gap-4 md:grid-cols-3">
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
                                        @if ($canRaiseHand)
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ __('Stato richiesta') }}</p>
                                                <p class="mt-2 text-sm font-medium" data-live-stream-hand-raise-status>{{ __('Nessuna richiesta attiva') }}</p>
                                            </div>
                                        @endif
                                    </div>

                                    <p class="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">
                                        {{ __('Descrizione') }}
                                    </p>
                                    <p class="mt-2 max-w-3xl text-sm leading-6 text-base-content/70">
                                        {{ $module->description ?: __('Nessuna descrizione disponibile per questo modulo live.') }}
                                    </p>
                                </div>
                            </div>

                            <input
                                type="radio"
                                name="live-stream-tabs"
                                class="tab"
                                aria-label="{{ __('Materiale didattico') }}"
                            />
                            <div class="tab-content border-base-300 bg-base-100 p-6">
                                <div class="space-y-4">
                                    <div>
                                        <h2 class="text-xl font-semibold">{{ __('Materiale didattico') }}</h2>
                                    </div>

                                    <div class="space-y-3" data-live-stream-documents-list></div>
                                    <p class="text-sm text-base-content/60" data-live-stream-documents-empty>
                                        {{ __('Nessun materiale didattico condiviso') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="min-h-0 border-t border-base-300 xl:border-t-0">
                <div class="h-full min-h-128 bg-base-100 p-2 xl:sticky xl:top-0 xl:max-h-screen">
                    <div class="tabs tabs-lift h-full flex-wrap content-start">
                        <input
                            type="radio"
                            name="live-stream-sidebar-tabs"
                            class="tab"
                            aria-label="{{ __('Discenti') }}"
                            checked="checked"
                        />
                        <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                            <div class="h-full overflow-y-auto space-y-4">
                                <div class="card rounded-box border border-base-300 bg-base-100">
                                    <div class="card-body gap-4 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <h3 class="text-sm font-semibold">{{ __('Dispositivi') }}</h3>
                                                <p class="text-xs text-base-content/60">{{ __('Controlla la tua anteprima') }}</p>
                                            </div>
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-sm btn-square"
                                                aria-label="{{ __('Impostazioni') }}"
                                                data-live-stream-preview-toggle
                                            >
                                                <x-lucide-settings class="h-4 w-4" />
                                            </button>
                                        </div>

                                        <div class="hidden space-y-3" data-live-stream-preview-panel>
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
                                                    <span class="text-xs text-base-content/60" data-live-stream-mic-label></span>
                                                </div>
                                                <progress class="progress progress-primary mt-3 w-full" value="0" max="100" data-live-stream-mic-meter></progress>
                                            </div>

                                            <p class="text-sm text-base-content/60" data-live-stream-device-status>
                                                {{ __('Consenti l\'accesso a videocamera e microfono per visualizzare l\'anteprima. Puoi continuare anche senza videocamera.') }}
                                            </p>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            <button type="button" class="btn btn-primary" data-live-stream-join-button>
                                                {{ __('Entra nella diretta') }}
                                            </button>
                                            @if ($canRaiseHand)
                                                <button
                                                    type="button"
                                                    class="btn btn-square btn-outline hidden"
                                                    aria-label="{{ __('Alza la mano') }}"
                                                    title="{{ __('Alza la mano') }}"
                                                    data-live-stream-hand-raise-button
                                                >
                                                    <x-lucide-hand class="h-4 w-4" />
                                                    <span class="sr-only">{{ __('Alza la mano') }}</span>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <div class="mb-3 flex items-center justify-between gap-3 px-1">
                                        <h3 class="text-sm font-semibold">{{ __('Discenti connessi') }}</h3>
                                        <span class="text-xs text-base-content/60" data-live-stream-participant-count>0</span>
                                    </div>
                                    <div class="space-y-3" data-live-stream-participant-list></div>
                                </div>
                            </div>
                        </div>

                        <input
                            type="radio"
                            name="live-stream-sidebar-tabs"
                            class="tab"
                            aria-label="{{ __('Chat') }}"
                        />
                        <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                            <div class="flex h-full flex-col border border-base-300 bg-base-200">
                                <div
                                    class="flex-1 space-y-3 overflow-y-auto px-4 py-4"
                                    data-live-stream-chat-messages
                                ></div>

                                <form class="border-t border-base-300 px-4 py-3" data-live-stream-chat-form>
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="text"
                                            name="body"
                                            class="input input-bordered w-full"
                                            placeholder="{{ __('Scrivi un messaggio...') }}"
                                            maxlength="1000"
                                            data-live-stream-chat-input
                                        >
                                        <button type="submit" class="btn btn-primary" data-live-stream-chat-submit>
                                            {{ __('Invia') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <div class="hidden" data-live-stream-audio-stage></div>

        <template data-live-stream-chat-template>
            <article class="chat chat-start">
                <div class="chat-image avatar">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-content">
                        <span data-chat-initials></span>
                    </div>
                </div>
                <div class="chat-header mb-1 flex items-center gap-2 text-sm">
                    <span class="font-semibold" data-chat-author></span>
                    <time class="text-xs text-base-content/50" data-chat-time></time>
                    @if ($canModerateChat)
                        <button
                            type="button"
                            class="btn btn-ghost btn-xs ml-auto hidden"
                            data-chat-delete
                            aria-label="{{ __('Rimuovi messaggio') }}"
                            title="{{ __('Rimuovi messaggio') }}"
                        >
                            <x-lucide-trash-2 class="h-3.5 w-3.5" />
                            <span class="sr-only">{{ __('Rimuovi messaggio') }}</span>
                        </button>
                    @endif
                </div>
                <div class="chat-bubble rounded-none bg-base-100 text-base-content" data-chat-bubble>
                    <p data-chat-body></p>
                </div>
            </article>
        </template>

        @if (data_get($liveStreamConfig, 'role') === 'user')
            <div class="fixed inset-0 z-50 hidden items-center justify-center bg-black/55 px-4 py-6" data-live-stream-poll-modal>
                <div class="w-full max-w-xl rounded-[1.75rem] border border-base-300 bg-base-100 p-6 shadow-2xl sm:p-8">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-base-content/45">{{ __('Sondaggio live') }}</p>
                        <h2 class="text-2xl font-semibold text-base-content" data-live-stream-poll-question></h2>
                        <p class="text-sm text-base-content/60">
                            {{ __('Seleziona una risposta e inviala per chiudere il sondaggio.') }}
                        </p>
                    </div>

                    <form class="mt-6 space-y-4" data-live-stream-poll-form>
                        <div class="space-y-3" data-live-stream-poll-options></div>
                        <p class="hidden text-sm text-error" data-live-stream-poll-error></p>

                        <div class="flex items-center justify-end gap-3">
                            <button type="submit" class="btn btn-primary" data-live-stream-poll-submit>
                                {{ __('Invia risposta') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </section>
</x-layouts.app>
