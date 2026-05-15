<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Review submission quiz')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.quiz.submissions.show', [$course, $module, $submission]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Back to detail') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Corso: :course. Modulo: :module.', ['course' => $course->title, 'module' => $module->title]) }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="rounded-lg bg-base-200 p-4 text-sm">
                    <p><span class="font-medium">{{ __('Utente rilevato:') }}</span> {{ $submission->user?->name ? $submission->user->name.' '.$submission->user->surname : __('Non rilevato') }}</p>
                    <p><span class="font-medium">{{ __('Stato OCR:') }}</span> {{ __(ucfirst($submission->status)) }}</p>
                </div>

                @if ($errors->has('submission'))
                    <div class="alert alert-error">
                        <span>{{ $errors->first('submission') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.courses.modules.quiz.submissions.finalize', [$course, $module, $submission]) }}" class="grid gap-4">
                    @csrf

                    @foreach ($module->quizQuestions as $index => $question)
                        @php
                            $extractedAnswer = $submission->answers->firstWhere('module_quiz_question_id', $question->getKey());
                        @endphp

                        <div class="rounded-lg border border-base-300 p-4">
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_14rem] md:items-start">
                                <div class="grid gap-2">
                                    <h2 class="font-semibold">{{ __('Domanda :number', ['number' => $index + 1]) }}</h2>
                                    <p class="text-sm text-base-content/80">{{ $question->text }}</p>
                                </div>

                                <div class="grid gap-2">
                                    <input type="hidden" name="answers[{{ $index }}][question_id]" value="{{ $question->getKey() }}">
                                    <label class="label p-0">
                                        <span class="label-text font-medium">{{ __('Risposta') }}</span>
                                    </label>
                                    <select name="answers[{{ $index }}][selected_option_key]" class="select select-bordered w-full">
                                        @foreach (['A', 'B', 'C', 'D'] as $optionKey)
                                            <option value="{{ $optionKey }}" @selected(old("answers.{$index}.selected_option_key", $extractedAnswer?->selected_option_key) === $optionKey)>
                                                {{ $optionKey }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <p class="text-xs text-base-content/60">
                                        {{ __('Confidence OCR: :confidence', ['confidence' => $extractedAnswer?->confidence !== null ? number_format((float) $extractedAnswer->confidence, 2) : '—']) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">{{ __('Conferma e finalizza') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
