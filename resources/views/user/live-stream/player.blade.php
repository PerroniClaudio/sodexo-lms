<x-layouts.app>
    @vite('resources/js/livestream-user.js')
    <section class="h-screen w-full overflow-hidden">
        <div class="grid h-full min-h-0 lg:grid-cols-3">
            <div class="min-h-0 lg:col-span-2">
                <div
                    class="h-full min-h-0 overflow-y-auto bg-base-200 lg:border-r lg:border-base-300"
                    data-livestream-user-main-scroll
                >
                    <div class="flex min-h-screen flex-col p-6 lg:p-8">
                        <div class="flex flex-1 flex-col gap-4">
                            <div class="flex min-h-0 flex-1 items-center justify-center border border-base-300 bg-neutral text-neutral-content">
                                <div class="flex h-full w-full items-center justify-center">
                                    <p class="text-center text-2xl font-semibold">{{ __('Player video principale') }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 xl:grid-cols-5">
                                <div class="border border-base-300 bg-neutral px-3 py-10 text-center text-sm text-neutral-content/70">
                                    {{ __('Camera 1') }}
                                </div>
                                <div class="border border-base-300 bg-neutral px-3 py-10 text-center text-sm text-neutral-content/70">
                                    {{ __('Camera 2') }}
                                </div>
                                <div class="border border-base-300 bg-neutral px-3 py-10 text-center text-sm text-neutral-content/70">
                                    {{ __('Camera 3') }}
                                </div>
                                <div class="border border-base-300 bg-neutral px-3 py-10 text-center text-sm text-neutral-content/70">
                                    {{ __('Camera 4') }}
                                </div>
                                <div class="border border-base-300 bg-neutral px-3 py-10 text-center text-sm text-neutral-content/70">
                                    {{ __('Camera 5') }}
                                </div>
                            </div>

                            <button
                                type="button"
                                class="flex items-center justify-center py-2 text-base-content/60"
                                aria-label="{{ __('Vai ai dettagli live') }}"
                                data-livestream-user-scroll-details
                            >
                                <x-lucide-chevron-down class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    <div class="px-6 pb-6 lg:px-8 lg:pb-8" data-livestream-user-details-section>
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
                                    <div>
                                        <h2 class="mt-2 text-3xl font-semibold">{{ __('Live in corso') }}</h2>
                                        <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">
                                            {{ __('Docente') }}
                                        </p>
                                        <p class="mt-2 text-base font-medium">{{ __('Da definire') }}</p>
                                        <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">
                                            {{ __('Descrizione') }}
                                        </p>
                                        <p class="mt-2 max-w-3xl text-sm leading-6 text-base-content/70">
                                            {{ __('Descrizione della live disponibile in questa sezione, con informazioni utili per seguire la sessione e consultare i contenuti condivisi dal docente.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <input
                                type="radio"
                                name="live-stream-tabs"
                                class="tab"
                                aria-label="{{ __('File allegati') }}"
                            />
                            <div class="tab-content border-base-300 bg-base-100 p-6">
                                <div class="space-y-4">
                                    <div class="border border-base-300 bg-base-200 p-4">
                                        <p class="text-sm font-medium">{{ __('Materiale lezione.pdf') }}</p>
                                        <p class="mt-1 text-sm text-base-content/70">
                                            {{ __('Documento allegato disponibile per il download.') }}
                                        </p>
                                    </div>

                                    <div class="border border-base-300 bg-base-200 p-4">
                                        <p class="text-sm font-medium">{{ __('Slide live.pptx') }}</p>
                                        <p class="mt-1 text-sm text-base-content/70">
                                            {{ __('Presentazione condivisa durante la sessione.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="min-h-0 lg:col-span-1">
                <div class="h-full min-h-[32rem] bg-base-100 p-2">
                    <div class="tabs tabs-lift h-full flex-wrap content-start">
                        <input
                            type="radio"
                            name="live-stream-sidebar-tabs"
                            class="tab"
                            aria-label="{{ __('Chat') }}"
                            checked="checked"
                        />
                        <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                            <div class="flex h-full flex-col border border-base-300 bg-base-200">
                                <div
                                    class="flex-1 space-y-3 overflow-y-auto px-4 py-4"
                                    data-livestream-user-chat-messages
                                ></div>

                                <div class="border-t border-base-300 px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="text"
                                            class="input input-bordered w-full"
                                            value="{{ __('Scrivi un messaggio...') }}"
                                            readonly
                                        >
                                        <button type="button" class="btn btn-primary" disabled>
                                            {{ __('Invia') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input
                            type="radio"
                            name="live-stream-sidebar-tabs"
                            class="tab"
                            aria-label="{{ __('Discenti') }}"
                        />
                        <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                            <div class="h-full space-y-4">
                                <div class="card rounded-box border border-base-300 bg-base-100">
                                    <div class="card-body gap-4 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="avatar">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-content">
                                                    <span>{{ __('MB') }}</span>
                                                </div>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium">{{ __('Marco Bianchi') }}</p>
                                                <p class="text-xs text-base-content/60">{{ __('Tu') }}</p>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-sm btn-square"
                                            aria-label="{{ __('Impostazioni') }}"
                                            data-livestream-user-settings-open
                                        >
                                            <x-lucide-settings class="h-4 w-4" />
                                        </button>
                                        </div>

                                        <div class="hidden space-y-3" data-livestream-user-settings-panel>
                                            <div class="overflow-hidden rounded-box border border-base-300 bg-neutral">
                                                <video
                                                    class="aspect-video w-full bg-neutral object-cover"
                                                    autoplay
                                                    muted
                                                    playsinline
                                                    data-livestream-user-preview
                                                ></video>
                                                <div
                                                    class="hidden aspect-video w-full items-center justify-center bg-neutral text-center text-sm text-neutral-content/70"
                                                    data-livestream-user-preview-empty
                                                >
                                                    {{ __('Anteprima video non disponibile') }}
                                                </div>
                                            </div>

                                            <div class="rounded-box border border-base-300 bg-base-200 p-4">
                                                <p class="text-sm font-medium">{{ __('Microfono') }}</p>

                                                <div class="mt-4 space-y-3">
                                                    <progress
                                                        class="progress progress-primary w-full"
                                                        value="0"
                                                        max="100"
                                                        data-livestream-user-mic-meter
                                                    ></progress>
                                                </div>
                                            </div>

                                            <p class="text-sm text-base-content/60" data-livestream-user-device-status>
                                                {{ __('Consenti l’accesso a videocamera e microfono per visualizzare l’anteprima.') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                        <div class="flex items-center gap-3">
                                            <div class="avatar">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-base-200 text-sm font-semibold text-base-content">
                                                    <span>{{ __('GR') }}</span>
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium">{{ __('Giulia R.') }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-base-200 text-sm font-semibold text-base-content">
                                                    <span>{{ __('LB') }}</span>
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium">{{ __('Luca B.') }}</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="avatar">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-base-200 text-sm font-semibold text-base-content">
                                                    <span>{{ __('EV') }}</span>
                                                </div>
                                            </div>
                                            <span class="text-sm font-medium">{{ __('Elena V.') }}</span>
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <template data-livestream-user-chat-template>
            <article class="chat chat-start">
                <div class="chat-image avatar">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-content">
                        <span data-chat-initials></span>
                    </div>
                </div>
                <div class="chat-header mb-1 flex items-center gap-2 text-sm">
                    <span class="font-semibold" data-chat-author></span>
                    <time class="text-xs text-base-content/50" data-chat-time></time>
                </div>
                <div class="chat-bubble rounded-none bg-base-100 text-base-content" data-chat-body data-chat-bubble></div>
            </article>
        </template>
    </section>
</x-layouts.app>
