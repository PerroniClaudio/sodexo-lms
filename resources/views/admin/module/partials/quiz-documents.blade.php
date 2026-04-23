<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-4">
        <h2 class="text-lg font-semibold">{{ __('Documenti Quiz') }}</h2>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.courses.modules.quiz.pdf.download', [$course, $module]) }}" class="btn btn-primary">
                <span>{{ __('Scarica PDF') }}</span>
            </a>
            <a href="{{ route('admin.courses.modules.quiz.answer-sheet.pdf.download', [$course, $module]) }}" class="btn btn-outline">
                <span>{{ __('Scarica PDF scheda risposte') }}</span>
            </a>
        </div>
    </div>
</div>
