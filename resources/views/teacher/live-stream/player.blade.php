<x-layouts.app>
    @vite('resources/js/livestream-teacher.js')

    <section class="h-screen w-full overflow-hidden" data-live-stream-root>
        <script type="application/json" data-live-stream-config>@json($liveStreamConfig)</script>

        <div class="grid h-full min-h-0 lg:grid-cols-3">
            <div class="min-h-0 lg:col-span-2">
                <div
                    class="h-full min-h-0 overflow-y-auto bg-base-200 lg:border-r lg:border-base-300"
                    data-livestream-user-main-scroll
                >
                    <div class="flex min-h-screen flex-col p-6 lg:p-8">
                        <div class="flex flex-1 flex-col gap-4">
                            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3" data-live-stream-teacher-grid></div>

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
                                name="teacher-live-stream-tabs"
                                class="tab"
                                aria-label="{{ __('Dettagli live') }}"
                                checked="checked"
                            />
                            <div class="tab-content border-base-300 bg-base-100 p-6">
                                <h2 class="text-3xl font-semibold">{{ $module->title }}</h2>
                                <p class="mt-2 text-sm text-base-content/70" data-live-stream-message>
                                    {{ __('Prepara l\'anteprima, poi avvia la diretta.') }}
                                </p>
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

                                <p class="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-base-content/50">
                                    {{ __('Descrizione') }}
                                </p>
                                <p class="mt-2 max-w-3xl text-sm leading-6 text-base-content/70">
                                    {{ $module->description ?: __('Nessuna descrizione disponibile per questo modulo live.') }}
                                </p>
                            </div>

                            <input
                                type="radio"
                                name="teacher-live-stream-tabs"
                                class="tab"
                                aria-label="{{ __('Materiale didattico') }}"
                            />
                            <div class="tab-content border-base-300 bg-base-100 p-6">
                                <div class="space-y-6">
                                    <div>
                                        <h2 class="text-2xl font-semibold">{{ __('Materiale didattico') }}</h2>
                                        <p class="mt-2 max-w-3xl text-sm text-base-content/70">
                                            {{ __('Carica PDF da condividere con i partecipanti durante la diretta. I file saranno disponibili subito anche per il download.') }}
                                        </p>
                                    </div>

                                    <form class="space-y-3" data-live-stream-document-form>
                                        <label class="form-control w-full">
                                            <span class="label-text text-sm font-medium">{{ __('Seleziona un PDF') }}</span>
                                            <input
                                                type="file"
                                                name="document"
                                                accept="application/pdf,.pdf"
                                                class="file-input file-input-bordered w-full"
                                                data-live-stream-document-input
                                            >
                                        </label>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="submit" class="btn btn-primary" data-live-stream-document-submit>
                                                {{ __('Carica PDF') }}
                                            </button>
                                            <p class="text-sm text-base-content/60" data-live-stream-document-feedback></p>
                                        </div>
                                    </form>

                                    <div class="space-y-3">
                                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-base-content/50">{{ __('PDF condivisi') }}</h3>
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
            </div>

            <aside class="min-h-0 lg:col-span-1">
                <div class="h-full min-h-128 bg-base-100 p-2">
                    <div class="tabs tabs-lift h-full flex-wrap content-start">
                        <input
                            type="radio"
                            name="teacher-live-stream-sidebar-tabs"
                            class="tab"
                            aria-label="{{ __('Discenti') }}"
                            checked="checked"
                        />
                        <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                            <div class="h-full overflow-y-auto space-y-4">
                                <div data-live-stream-preview-panel>
                                    <div class="card rounded-box border border-base-300 bg-base-100">
                                        <div class="card-body gap-4 p-4">
                                            <div class="space-y-3">
                                                <button type="button" class="btn btn-outline w-full" data-live-stream-preview-request>
                                                    {{ __('Attiva videocamera e microfono') }}
                                                </button>

                                                <div class="hidden space-y-3" data-live-stream-preview-content>
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
                                                            <div>
                                                                <p class="text-sm font-medium">{{ __('Microfono') }}</p>
                                                                <span class="text-xs text-base-content/60" data-live-stream-mic-label></span>
                                                            </div>
                                                        </div>
                                                        <progress class="progress progress-primary mt-3 w-full" value="0" max="100" data-live-stream-mic-meter></progress>
                                                    </div>

                                                    <div class="rounded-box border border-base-300 bg-base-200 p-4">
                                                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline btn-sm w-full gap-2"
                                                                data-live-stream-teacher-local-mic-toggle
                                                                aria-label="{{ __('Disattiva microfono') }}"
                                                                title="{{ __('Disattiva microfono') }}"
                                                                disabled
                                                            >
                                                                <x-lucide-mic class="h-4 w-4" />
                                                                <span>{{ __('Microfono') }}</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline btn-sm w-full gap-2"
                                                                data-live-stream-audio-output-open
                                                                aria-label="{{ __('Output audio') }}"
                                                                title="{{ __('Output audio') }}"
                                                            >
                                                                <x-lucide-speaker class="h-4 w-4" />
                                                                <span class="sr-only">{{ __('Output audio') }}</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline btn-sm hidden w-full"
                                                                data-live-stream-background-button
                                                                aria-label="{{ __('Cambia sfondo') }}"
                                                                title="{{ __('Cambia sfondo') }}"
                                                            >
                                                                <x-lucide-image class="h-4 w-4" />
                                                                <span class="sr-only" data-live-stream-background-button-label>{{ __('Sfondo') }}</span>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                class="btn btn-outline btn-sm hidden w-full gap-2"
                                                                data-live-stream-screen-share-toggle
                                                                disabled
                                                            >
                                                                <x-lucide-monitor-up class="h-4 w-4" />
                                                                <span>{{ __('Condividi schermo') }}</span>
                                                            </button>
                                                        </div>
                                                        <p class="mt-3 hidden text-xs text-base-content/60" data-live-stream-screen-share-status></p>
                                                    </div>

                                                    <div class="alert alert-warning hidden text-sm" data-live-stream-background-warning></div>

                                                    <div class="space-y-3">
                                                        <button type="button" class="btn btn-primary btn-lg w-full" data-live-stream-start-button>
                                                            {{ __('Avvia diretta') }}
                                                        </button>
                                                        <button type="button" class="btn btn-error btn-lg hidden w-full" data-live-stream-end-button disabled>
                                                            {{ __('Termina diretta') }}
                                                        </button>
                                                    </div>
                                                </div>

                                                <p class="text-sm text-base-content/60" data-live-stream-device-status>
                                                    {{ __('Consenti l’accesso a videocamera e microfono per visualizzare l’anteprima. Puoi continuare anche senza videocamera.') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <dialog class="modal" data-live-stream-background-modal>
                                    <div class="modal-box w-11/12 max-w-5xl">
                                        <h3 class="text-lg font-semibold">{{ __('Sfondo videocamera') }}</h3>
                                        <p class="mt-2 text-sm text-base-content/60">
                                            {{ __('Scegli come mostrare il video locale senza riavviare videocamera o diretta.') }}
                                        </p>

                                        <div class="mt-6 grid gap-3 md:grid-cols-2">
                                            <button type="button" class="btn btn-outline justify-start gap-3" data-live-stream-background-option="none">
                                                <span>{{ __('Nessuno sfondo') }}</span>
                                            </button>
                                            <button type="button" class="btn btn-outline justify-start gap-3" data-live-stream-background-option="blur">
                                                <span>{{ __('Sfocatura') }}</span>
                                            </button>
                                        </div>

                                        <div class="mt-6 space-y-4">
                                            <div class="flex flex-wrap items-center justify-between gap-3">
                                                <h4 class="text-sm font-semibold">{{ __('Immagini disponibili') }}</h4>
                                                <label class="btn btn-outline btn-sm">
                                                    <input type="file" accept="image/*" class="hidden" data-live-stream-background-upload-input>
                                                    <span>{{ __('Carica immagine temporanea') }}</span>
                                                </label>
                                            </div>
                                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-live-stream-background-image-options></div>
                                            <p class="text-sm text-base-content/60" data-live-stream-background-image-options-empty>
                                                {{ __('Nessuna immagine trovata nella cartella designata.') }}
                                            </p>
                                        </div>
                                    </div>

                                    <form method="dialog" class="modal-backdrop">
                                        <button type="submit" aria-label="{{ __('Chiudi') }}">
                                            <span class="sr-only">{{ __('Chiudi') }}</span>
                                        </button>
                                    </form>
                                </dialog>

                                <dialog class="modal" data-live-stream-audio-output-modal>
                                    <div class="modal-box">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <h3 class="text-lg font-semibold">{{ __('Seleziona output audio') }}</h3>
                                            </div>

                                            <button type="button" class="btn btn-ghost btn-sm btn-square" data-live-stream-audio-output-close aria-label="{{ __('Chiudi') }}">
                                                <x-lucide-x class="h-4 w-4" />
                                            </button>
                                        </div>

                                        <p class="mt-4 hidden text-sm text-base-content/60" data-live-stream-audio-output-status></p>
                                        <div class="mt-6 grid gap-3" data-live-stream-audio-output-list></div>
                                    </div>

                                    <form method="dialog" class="modal-backdrop">
                                        <button type="submit" aria-label="{{ __('Chiudi') }}">
                                            <span class="sr-only">{{ __('Chiudi') }}</span>
                                        </button>
                                    </form>
                                </dialog>

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
                            name="teacher-live-stream-sidebar-tabs"
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

                        <input
                            type="radio"
                            name="teacher-live-stream-sidebar-tabs"
                            class="tab"
                            aria-label="{{ __('Sondaggi') }}"
                        />
                        <div class="tab-content h-[calc(100%-4rem)] border-base-300 bg-base-100 p-4">
                            <div class="flex h-full flex-col gap-4 overflow-y-auto">
                                <div class="rounded-box border border-base-300 bg-base-200 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="space-y-1">
                                            <h3 class="text-sm font-semibold">{{ __('Nuovo sondaggio') }}</h3>
                                            <p class="text-xs text-base-content/60">
                                                {{ __('Pubblica una domanda a risposta multipla per i partecipanti connessi. Il nuovo sondaggio chiude automaticamente quello precedente.') }}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm shrink-0"
                                            data-live-stream-poll-toggle
                                            aria-expanded="false"
                                            aria-controls="live-stream-poll-composer"
                                        >
                                            {{ __('Crea sondaggio') }}
                                        </button>
                                    </div>

                                    <form
                                        id="live-stream-poll-composer"
                                        class="mt-4 hidden space-y-3"
                                        data-live-stream-poll-form
                                    >
                                        <label class="form-control w-full">
                                            <span class="label-text text-sm font-medium">{{ __('Domanda') }}</span>
                                            <textarea
                                                name="question"
                                                rows="3"
                                                class="textarea textarea-bordered w-full"
                                                maxlength="1000"
                                                placeholder="{{ __('Scrivi la domanda del sondaggio') }}"
                                                data-live-stream-poll-question-input
                                            ></textarea>
                                        </label>

                                        <div class="grid gap-3">
                                            @foreach (['A', 'B', 'C', 'D'] as $optionLabel)
                                                <label class="form-control w-full">
                                                    <span class="label-text text-sm font-medium">{{ __('Risposta :label', ['label' => $optionLabel]) }}</span>
                                                    <input
                                                        type="text"
                                                        name="options[]"
                                                        class="input input-bordered w-full"
                                                        maxlength="255"
                                                        placeholder="{{ __('Inserisci la risposta :label', ['label' => $optionLabel]) }}"
                                                        data-live-stream-poll-option-input
                                                    >
                                                </label>
                                            @endforeach
                                        </div>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <button type="submit" class="btn btn-primary" data-live-stream-poll-submit>
                                                {{ __('Pubblica sondaggio') }}
                                            </button>
                                            <p class="text-sm text-base-content/60" data-live-stream-poll-feedback></p>
                                        </div>
                                    </form>
                                </div>

                                <div class="flex min-h-0 flex-1 flex-col gap-3">
                                    <div class="flex items-center justify-between gap-3 px-1">
                                        <h3 class="text-sm font-semibold">{{ __('Sondaggi pubblicati') }}</h3>
                                    </div>
                                    <div class="space-y-3" data-live-stream-polls-list></div>
                                    <p class="text-sm text-base-content/60" data-live-stream-polls-empty>
                                        {{ __('Nessun sondaggio pubblicato') }}
                                    </p>
                                </div>
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
                </div>
                <div class="chat-bubble rounded-none bg-base-100 text-base-content" data-chat-bubble>
                    <p data-chat-body></p>
                </div>
            </article>
        </template>
    </section>
</x-layouts.app>
