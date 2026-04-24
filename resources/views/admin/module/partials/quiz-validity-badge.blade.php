<div id="quiz-validity-badge" class="flex items-center gap-3">
    <span data-valid-badge class="badge badge-sm badge-success" style="display: {{ $module->isValidQuiz() ? 'inline-flex' : 'none' }};">{{ __('Valido') }}</span>
    <span data-invalid-badge class="badge badge-sm badge-error whitespace-nowrap" style="display: {{ $module->isValidQuiz() ? 'none' : 'inline-flex' }};">{{ __('Non valido') }}</span>
    <span data-invalid-reason class="text-xs text-error" style="display: {{ $module->isValidQuiz() ? 'none' : 'inline' }};">{{ __('Deve avere almeno una domanda valida e il punteggio di superamento non può essere più alto del punteggio massimo.') }}</span>
</div>