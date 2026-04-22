<x-layouts.admin>
    @vite('resources/js/livestream-teacher.js')

    <section class="min-h-screen w-full bg-base-100" data-live-stream-root>
        <script type="application/json" data-live-stream-config>@json($liveStreamConfig)</script>

        <div class="grid min-h-screen gap-4 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.12),_transparent_38%),linear-gradient(to_bottom,_hsl(var(--b1)),_hsl(var(--b2)))] p-4 xl:grid-cols-[minmax(0,1fr)_26rem]">
            <div class="min-h-0 space-y-4">
                <div class="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <span class="badge badge-neutral badge-outline">{{ __('Regia') }}</span>
                            <h1 class="mt-4 text-3xl font-semibold">{{ $module->title }}</h1>
                            <p class="mt-2 text-sm text-base-content/70" data-live-stream-message>
                                {{ __('Prepara la regia e avvia la live quando il feed MUX è pronto.') }}
                            </p>
                        </div>

                        <div class="text-sm text-base-content/60">
                            {{ $module->appointment_start_time?->format('d/m/Y H:i') }}
                        </div>
                    </div>
                </div>

                <div class="tabs tabs-lift">
                    <input type="radio" name="regia-main-tabs" class="tab" aria-label="{{ __('Player MUX') }}" checked="checked" />
                    <div class="tab-content border-base-300 bg-base-100 p-4">
                        <div class="aspect-video w-full overflow-hidden rounded-[1.75rem]" data-live-stream-mux-stage></div>
                    </div>

                    <input type="radio" name="regia-main-tabs" class="tab" aria-label="{{ __('Videocamere') }}" />
                    <div class="tab-content border-base-300 bg-base-100 p-4">
                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" data-live-stream-teacher-grid></div>
                    </div>
                </div>
            </div>

            <aside class="min-h-0">
                <div class="tabs tabs-lift h-full flex-wrap content-start">
                    <input type="radio" name="regia-side-tabs" class="tab" aria-label="{{ __('Regia') }}" checked="checked" />
                    <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                        <div class="space-y-4 overflow-y-auto">
                            <div class="rounded-box border border-base-300 bg-base-200 p-4">
                                <div class="flex gap-3">
                                    <button type="button" class="btn btn-primary flex-1" data-live-stream-start-button>
                                        {{ __('Avvia live') }}
                                    </button>
                                    <button type="button" class="btn btn-error hidden flex-1" data-live-stream-end-button>
                                        {{ __('Termina live') }}
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold">{{ __('Partecipanti') }}</h3>
                                    <span class="text-xs text-base-content/60" data-live-stream-participant-count>0</span>
                                </div>
                                <div class="space-y-3" data-live-stream-participant-list></div>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                <h3 class="text-sm font-semibold">{{ __('Materiale didattico') }}</h3>
                                <form class="mt-4 space-y-3" data-live-stream-document-form>
                                    <input type="file" name="document" accept="application/pdf,.pdf" class="file-input file-input-bordered w-full" data-live-stream-document-input>
                                    <button type="submit" class="btn btn-primary w-full" data-live-stream-document-submit>{{ __('Carica PDF') }}</button>
                                    <p class="text-sm text-base-content/60" data-live-stream-document-feedback></p>
                                </form>
                                <div class="mt-4 space-y-3" data-live-stream-documents-list></div>
                                <p class="mt-3 text-sm text-base-content/60" data-live-stream-documents-empty>{{ __('Nessun materiale didattico condiviso') }}</p>
                            </div>
                        </div>
                    </div>

                    <input type="radio" name="regia-side-tabs" class="tab" aria-label="{{ __('Chat') }}" />
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

                    <input type="radio" name="regia-side-tabs" class="tab" aria-label="{{ __('Sondaggi') }}" />
                    <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                        <div class="space-y-4 overflow-y-auto">
                            <div class="rounded-box border border-base-300 bg-base-200 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-semibold">{{ __('Nuovo sondaggio') }}</h3>
                                    <button type="button" class="btn btn-primary btn-sm" data-live-stream-poll-toggle>{{ __('Crea sondaggio') }}</button>
                                </div>
                                <form class="mt-4 hidden space-y-3" data-live-stream-poll-form>
                                    <textarea name="question" rows="3" class="textarea textarea-bordered w-full" maxlength="1000" placeholder="{{ __('Scrivi la domanda del sondaggio') }}" data-live-stream-poll-question-input></textarea>
                                    @foreach (['A', 'B', 'C', 'D'] as $optionLabel)
                                        <input type="text" name="options[]" class="input input-bordered w-full" maxlength="255" placeholder="{{ __('Risposta :label', ['label' => $optionLabel]) }}" data-live-stream-poll-option-input>
                                    @endforeach
                                    <button type="submit" class="btn btn-primary w-full" data-live-stream-poll-submit>{{ __('Pubblica sondaggio') }}</button>
                                    <p class="text-sm text-base-content/60" data-live-stream-poll-feedback></p>
                                </form>
                            </div>

                            <div class="space-y-3" data-live-stream-polls-list></div>
                            <p class="text-sm text-base-content/60" data-live-stream-polls-empty>{{ __('Nessun sondaggio pubblicato') }}</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>

        <div class="hidden" data-live-stream-audio-stage></div>

        <dialog id="regia-live-modal" class="modal" data-live-stream-regia-modal>
            <div class="modal-box w-11/12 max-w-2xl rounded-3xl border border-base-300 bg-base-100 p-6 shadow-xl">
                <h2 class="text-2xl font-semibold">{{ __('Credenziali live MUX') }}</h2>
                <p class="mt-2 text-sm text-base-content/70">{{ __('Configura il software di regia con questi valori. Il modal resta aperto finché non lo chiudi.') }}</p>

                <div class="mt-6 grid gap-4">
                    <div class="rounded-box border border-base-300 bg-base-200 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">{{ __('Ingest URL') }}</p>
                        <p class="mt-2 font-mono text-sm" data-live-stream-regia-ingest-url></p>
                    </div>
                    <div class="rounded-box border border-base-300 bg-base-200 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">{{ __('Stream Key') }}</p>
                        <p class="mt-2 font-mono text-sm" data-live-stream-regia-stream-key></p>
                    </div>
                    <div class="rounded-box border border-base-300 bg-base-200 p-4">
                        <p class="text-xs uppercase tracking-[0.2em] text-base-content/50">{{ __('Playback ID') }}</p>
                        <p class="mt-2 font-mono text-sm" data-live-stream-regia-playback-id></p>
                    </div>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button type="submit" class="btn btn-ghost" data-live-stream-regia-modal-close>
                            {{ __('Chiudi') }}
                        </button>
                    </form>
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button type="submit">{{ __('Chiudi') }}</button>
            </form>
        </dialog>

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
</x-layouts.admin>
