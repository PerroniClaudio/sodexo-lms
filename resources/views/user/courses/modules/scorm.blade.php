<template id="tpl-scorm">
    <section class="grid gap-6" data-scorm-module-root>
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <h2 class="card-title">{{ __('Pacchetti SCORM del modulo') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Consulta i pacchetti disponibili, lo stato della tua fruizione e apri il player del singolo contenuto senza lasciare il corso.') }}
                        </p>
                    </div>

                    <button type="button" class="btn btn-outline btn-sm" data-scorm-refresh>
                        <x-lucide-refresh-cw class="h-4 w-4" />
                        <span>{{ __('Aggiorna dati') }}</span>
                    </button>
                </div>

                <div class="alert alert-info hidden" data-scorm-feedback>
                    <x-lucide-info class="h-5 w-5" />
                    <span data-scorm-feedback-text>{{ __('Aggiornamento in corso...') }}</span>
                </div>

                <div class="flex items-center justify-center py-10" data-scorm-loading>
                    <span class="loading loading-spinner loading-lg"></span>
                </div>

                <div class="hidden rounded-box border border-error/30 bg-error/5 p-4 text-sm text-error" data-scorm-error>
                    {{ __('Impossibile recuperare i dettagli dei pacchetti SCORM. Riprova tra qualche istante.') }}
                </div>

                <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70" data-scorm-empty>
                    {{ __('Nessun pacchetto SCORM disponibile per questo modulo.') }}
                </div>

                <div class="hidden grid gap-4" data-scorm-list></div>
            </div>
        </div>

        <template data-scorm-package-template>
            <article class="rounded-box border border-base-300 bg-base-100 p-4 shadow-sm" data-scorm-package-card>
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-4">
                        <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                            <div class="space-y-1">
                                <h3 class="text-base font-semibold" data-scorm-package-title></h3>
                                <p class="text-sm text-base-content/70" data-scorm-package-description></p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge badge-outline" data-scorm-package-version></span>
                                <span class="badge badge-ghost" data-scorm-package-status></span>
                                <span class="badge badge-primary badge-outline" data-scorm-package-learner-status></span>
                                <span class="badge badge-success hidden" data-scorm-package-completed>{{ __('Completato') }}</span>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Progresso') }}</p>
                                <div class="mt-2 hidden space-y-2" data-scorm-package-progress-block>
                                    <progress class="progress progress-primary w-full" max="100" value="0" data-scorm-package-progress-bar></progress>
                                    <p class="text-sm text-base-content/80" data-scorm-package-progress-value></p>
                                </div>
                                <p class="mt-2 text-sm text-base-content/60" data-scorm-package-progress-empty>{{ __('Avanzamento non disponibile') }}</p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Punteggio') }}</p>
                                <p class="mt-2 text-sm text-base-content/80" data-scorm-package-score>{{ __('Non disponibile') }}</p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Tempo modulo') }}</p>
                                <p class="mt-2 text-sm text-base-content/80" data-scorm-package-module-time></p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Ultima posizione') }}</p>
                                <p class="mt-2 break-all text-sm text-base-content/80" data-scorm-package-location>{{ __('Non disponibile') }}</p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Sessione') }}</p>
                                <p class="mt-2 text-sm text-base-content/80" data-scorm-package-session-status>{{ __('Non avviata') }}</p>
                                <p class="mt-1 text-xs text-base-content/60" data-scorm-package-last-activity>{{ __('Nessuna attivit&agrave; registrata') }}</p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-200/40 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Contenuto') }}</p>
                                <p class="mt-2 text-sm text-base-content/80" data-scorm-package-sco-count></p>
                                <p class="mt-1 text-xs text-base-content/60" data-scorm-package-resource-count></p>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Identificativo') }}</p>
                                <p class="mt-2 break-all text-sm text-base-content/80" data-scorm-package-identifier></p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Entry point') }}</p>
                                <p class="mt-2 break-all text-sm text-base-content/80" data-scorm-package-entry-point></p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('SCO corrente') }}</p>
                                <p class="mt-2 break-all text-sm text-base-content/80" data-scorm-package-sco-identifier></p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Organizzazione di default') }}</p>
                                <p class="mt-2 break-all text-sm text-base-content/80" data-scorm-package-organization></p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Resume') }}</p>
                                <p class="mt-2 text-sm text-base-content/80" data-scorm-package-resume></p>
                            </div>

                            <div class="rounded-box border border-base-300 bg-base-100 p-3">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/50">{{ __('Tracking SCORM') }}</p>
                                <p class="mt-2 text-sm text-base-content/80" data-scorm-package-tracked-time></p>
                            </div>
                        </div>

                        <div class="hidden rounded-box border border-error/30 bg-error/5 p-3" data-scorm-package-error-block>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-error">{{ __('Dettaglio errore') }}</p>
                            <p class="mt-2 text-sm text-error" data-scorm-package-error-text></p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-start">
                        <a href="#" class="btn btn-primary btn-sm hidden" data-scorm-package-player-link>
                            <x-lucide-play class="h-4 w-4" />
                            <span>{{ __('Apri player') }}</span>
                        </a>
                    </div>
                </div>
            </article>
        </template>
    </section>
</template>
