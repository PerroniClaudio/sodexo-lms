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
            <a href="{{ route('admin.courses.modules.quiz.submissions.index', [$course, $module, 'source_type' => 'upload']) }}" class="btn btn-outline">
                <span>{{ __('Visualizza correzione documento OCR') }}</span>
            </a>
        </div>

        <form method="POST" action="{{ route('admin.courses.modules.quiz.submissions.store', [$course, $module]) }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-[1fr_auto] md:items-end">
            @csrf
            <div class="grid gap-2">
                <label for="quiz-submission" class="label p-0">
                    <span class="label-text font-medium">{{ __('Carica PDF compilato') }}</span>
                </label>
                <input
                    id="quiz-submission"
                    name="submission"
                    type="file"
                    accept="application/pdf"
                    class="file-input file-input-bordered w-full @error('submission') file-input-error @enderror"
                >
                @error('submission')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary">
                <span>{{ __('Avvia correzione') }}</span>
            </button>
        </form>

        {{-- @if ($recentQuizSubmissions->isNotEmpty())
            <div class="grid gap-3">
                <h3 class="text-base font-semibold">{{ __('Submission recenti') }}</h3>

                @foreach ($recentQuizSubmissions as $submission)
                    <div class="flex flex-col gap-2 rounded-lg border border-base-300 p-3 md:flex-row md:items-center md:justify-between">
                        <div class="grid gap-1">
                            <p class="font-medium">
                                {{ $submission->user?->name ? $submission->user->name.' '.$submission->user->surname : __('Utente non rilevato') }}
                            </p>
                            <p class="text-sm text-base-content/70">
                                {{ __('Stato: :status', ['status' => __(ucfirst($submission->status))]) }} · {{ $submission->created_at?->format('d/m/Y H:i') }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.courses.modules.quiz.submissions.show', [$course, $module, $submission]) }}" class="btn btn-sm btn-outline">
                                {{ __('Dettaglio') }}
                            </a>

                            @if ($submission->status === \App\Models\ModuleQuizSubmission::STATUS_NEEDS_REVIEW)
                                <a href="{{ route('admin.courses.modules.quiz.submissions.review', [$course, $module, $submission]) }}" class="btn btn-sm btn-primary">
                                    {{ __('Review') }}
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif --}}
    </div>
</div>
