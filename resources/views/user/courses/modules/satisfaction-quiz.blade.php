{{-- Componente per quiz di gradimento --}}
<template id="tpl-quiz">
    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div id="quiz-loading" class="flex items-center justify-center py-12">
                <span class="loading loading-spinner loading-lg"></span>
            </div>
            <div id="quiz-content" class="hidden">
                <form id="quiz-form" class="flex flex-col gap-6">
                    <div id="quiz-questions"></div>
                    <div class="flex justify-end">
                        <button type="submit" id="quiz-submit-btn" class="btn btn-primary">
                            {{ __('Invia risposte') }}
                        </button>
                    </div>
                </form>
            </div>
            <div id="quiz-result" class="hidden"></div>
        </div>
    </div>
</template>

{{-- Script specifico per il quiz di gradimento --}}
@vite('resources/js/modules/module-satisfaction-quiz.js')
