<x-layouts.app>
    @vite('resources/js/livestream-teacher.js')

    <section class="min-h-screen w-full bg-base-100" data-live-stream-root>
        <script type="application/json" data-live-stream-config>@json($liveStreamConfig)</script>

        <div class="grid min-h-screen gap-4 p-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <div class="min-h-0 space-y-4">
                <div class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm">
                    <h1 class="text-3xl font-semibold">{{ $module->title }}</h1>
                    <p class="mt-2 text-sm text-base-content/70" data-live-stream-message>
                        {{ __('Entra nella diretta per vedere il feed MUX e i partecipanti collegati.') }}
                    </p>
                </div>

                <div class="tabs tabs-lift">
                    <input type="radio" name="teacher-mux-main-tabs" class="tab" aria-label="{{ __('Player MUX') }}" checked="checked" />
                    <div class="tab-content border-base-300 bg-base-100 p-4">
                        <div class="aspect-video w-full overflow-hidden rounded-[1.75rem]" data-live-stream-mux-stage></div>
                    </div>

                    <input type="radio" name="teacher-mux-main-tabs" class="tab" aria-label="{{ __('Videocamere discenti') }}" />
                    <div class="tab-content border-base-300 bg-base-100 p-4">
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" data-live-stream-teacher-grid></div>
                    </div>
                </div>
            </div>

            <aside class="min-h-0">
                <div class="tabs tabs-lift h-full flex-wrap content-start">
                    <input type="radio" name="teacher-mux-side-tabs" class="tab" aria-label="{{ __('Discenti') }}" checked="checked" />
                    <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                        <div class="space-y-4 overflow-y-auto">
                            <div class="rounded-box border border-base-300 bg-base-200 p-4">
                                <button type="button" class="btn btn-primary w-full" data-live-stream-start-button>
                                    {{ __('Entra nella diretta') }}
                                </button>
                            </div>

                            <div>
                                <div class="mb-3 flex items-center justify-between gap-3 px-1">
                                    <h3 class="text-sm font-semibold">{{ __('Partecipanti connessi') }}</h3>
                                    <span class="text-xs text-base-content/60" data-live-stream-participant-count>0</span>
                                </div>
                                <div class="space-y-3" data-live-stream-participant-list></div>
                            </div>
                        </div>
                    </div>

                    <input type="radio" name="teacher-mux-side-tabs" class="tab" aria-label="{{ __('Chat') }}" />
                    <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                        <div class="flex h-full flex-col border border-base-300 bg-base-200">
                            <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4" data-live-stream-chat-messages></div>
                            <form class="border-t border-base-300 px-4 py-3" data-live-stream-chat-form>
                                <div class="flex items-center gap-3">
                                    <input type="text" name="body" class="input input-bordered w-full" maxlength="1000" placeholder="{{ __('Scrivi un messaggio...') }}" data-live-stream-chat-input>
                                    <button type="submit" class="btn btn-primary" data-live-stream-chat-submit>{{ __('Invia') }}</button>
                                </div>
                            </form>
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
                </div>
                <div class="chat-bubble rounded-none bg-base-100 text-base-content" data-chat-bubble>
                    <p data-chat-body></p>
                </div>
            </article>
        </template>
    </section>
</x-layouts.app>
