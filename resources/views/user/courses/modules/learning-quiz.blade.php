{{-- Componente per quiz di apprendimento --}}

@php
    $quizAccessGateActive = (bool) ($quizAccessGate['active'] ?? false);
@endphp

@if ($quizAccessGateActive)
    <div id="quiz-access-gate" class="card border border-warning/40 bg-warning/10 shadow-sm">
        <div class="card-body gap-4">
            <div class="alert alert-warning">
                <x-lucide-alert-triangle class="h-5 w-5" />
                <span>{{ __('Questo quiz sarà disponibile dopo il tempo di attesa previsto dal modulo precedente.') }}</span>
            </div>

            <div class="stats stats-vertical shadow sm:stats-horizontal">
                <div class="stat">
                    <div class="stat-title">{{ __('Modulo precedente') }}</div>
                    <div class="stat-value text-lg" data-quiz-access-gate-previous-module>{{ $quizAccessGate['previous_module_title'] }}</div>
                </div>
                <div class="stat">
                    <div class="stat-title">{{ __('Tempo residuo') }}</div>
                    <div class="stat-value text-primary" data-quiz-access-gate-timer>--:--:--</div>
                </div>
            </div>

            <p class="text-sm text-base-content/70">
                {{ __('La pagina si aggiornerà automaticamente allo scadere del timer.') }}
            </p>
        </div>
    </div>
@endif

{{-- UI iniziale: stato del quiz --}}
<div id="quiz-status" @class(['card border border-base-300 bg-base-100 shadow-sm', 'hidden' => $quizAccessGateActive])>
    <div class="card-body gap-6">
        <div id="status-loading" class="flex items-center justify-center py-12">
            <span class="loading loading-spinner loading-lg"></span>
        </div>
    </div>
</div>

{{-- UI del quiz attivo --}}
<div id="quiz-active" class="hidden">
    <div class="card border border-base-300 bg-base-100 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="alert alert-warning">
                <x-lucide-alert-triangle class="h-5 w-5" />
                <span><strong>Attenzione:</strong> Non ricaricare la pagina e non usare i pulsanti di navigazione del browser durante il test, altrimenti perderai il tentativo!</span>
            </div>
        </div>
    </div>

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold" id="question-progress">Domanda <span id="current-question">-</span> di <span id="total-questions">-</span></h3>
            </div>

            <div id="question-loading" class="flex items-center justify-center py-12">
                <span class="loading loading-spinner loading-lg"></span>
            </div>

            <div id="question-content" class="hidden">
                <form id="question-form" class="flex flex-col gap-6">
                    <div id="question-text" class="text-lg font-medium"></div>
                    <div id="question-answers" class="flex flex-col gap-3"></div>
                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Conferma risposta') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- UI risultato finale --}}
<div id="quiz-result" class="hidden card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-6">
        <div id="result-content"></div>
    </div>
</div>

<template data-learning-quiz-status-template>
    <div class="flex flex-col gap-4">
        <div>
            <h3 class="text-lg font-semibold" data-quiz-status-title></h3>
        </div>

        <div class="stats shadow" data-quiz-status-stats></div>

        <div class="hidden" data-quiz-past-attempts>
            <div class="divider">{{ __('Tentativi precedenti') }}</div>
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Data') }}</th>
                            <th>{{ __('Punteggio') }}</th>
                            <th>{{ __('Risultato') }}</th>
                        </tr>
                    </thead>
                    <tbody data-quiz-past-attempts-body></tbody>
                </table>
            </div>
        </div>

        <div class="hidden alert alert-success" data-quiz-passed-alert>
            <span>{{ __('Quiz completato con successo!') }}</span>
        </div>

        <div class="hidden alert alert-error" data-quiz-exhausted-alert>
            <span>{{ __('Hai esaurito i tentativi disponibili.') }}</span>
        </div>

        <div class="hidden mt-4 flex justify-end" data-quiz-start-action>
            <button type="button" id="start-quiz-btn" class="btn btn-primary">{{ __('Inizia il test') }}</button>
        </div>
    </div>
</template>

<template data-learning-quiz-stat-template>
    <div class="stat">
        <div class="stat-title" data-quiz-stat-title></div>
        <div class="stat-value" data-quiz-stat-value></div>
    </div>
</template>

<template data-learning-quiz-attempt-row-template>
    <tr>
        <td data-quiz-attempt-date></td>
        <td data-quiz-attempt-score></td>
        <td>
            <span class="badge h-fit" data-quiz-attempt-result></span>
        </td>
    </tr>
</template>

<template data-learning-quiz-answer-template>
    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-base-300 p-4 hover:bg-base-200">
        <input type="radio" class="radio radio-primary" name="answer" value="" required>
        <span data-quiz-answer-text></span>
    </label>
</template>

<template data-learning-quiz-submit-loading-template>
    <span class="loading loading-spinner loading-sm"></span>
    <span>{{ __('Salvataggio...') }}</span>
</template>

<template data-learning-quiz-result-alert-template>
    <div class="alert">
        <svg data-quiz-result-icon xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></svg>
        <div>
            <div class="font-semibold" data-quiz-result-message></div>
            <div class="text-sm" data-quiz-result-score></div>
        </div>
    </div>
</template>

<template data-learning-quiz-next-module-template>
    <div class="mt-4 flex justify-end gap-4">
        <a href="#" class="btn btn-primary" data-quiz-next-module-link>
            <span data-quiz-next-module-title></span>
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/>
                <path d="m12 5 7 7-7 7"/>
            </svg>
        </a>
    </div>
</template>

<template data-learning-quiz-retry-template>
    <div class="mt-4 flex justify-center gap-4">
        <button type="button" class="btn btn-primary" data-quiz-retry-button>{{ __('Riprova') }}</button>
    </div>
</template>

{{-- Modal di conferma inizio quiz --}}
<dialog id="start-quiz-modal" class="modal">
    <div class="modal-box">
        <h3 class="font-bold text-lg">{{ __('Inizia il quiz') }}</h3>
        <p class="py-4">
            {{ __('Sei sicuro di voler iniziare il quiz?') }}<br>
            <strong class="text-error">{{ __('Attenzione:') }}</strong> {{ __('Una volta iniziato, non potrai ricaricare la pagina o navigare altrove senza perdere il tentativo.') }}
        </p>
        <div class="modal-action">
            <form method="dialog" class="flex gap-2">
                <button class="btn">{{ __('Annulla') }}</button>
                <button type="button" id="confirm-start-quiz" class="btn btn-primary">{{ __('Inizia') }}</button>
            </form>
        </div>
    </div>
</dialog>
