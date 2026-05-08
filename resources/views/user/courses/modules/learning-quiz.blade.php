{{-- Componente per quiz di apprendimento --}}

{{-- UI iniziale: stato del quiz --}}
<div id="quiz-status" class="card border border-base-300 bg-base-100 shadow-sm">
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

{{-- Script specifico per il quiz di apprendimento --}}
@vite('resources/js/modules/module-learning-quiz.js')
