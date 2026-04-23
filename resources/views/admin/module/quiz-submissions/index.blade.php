<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Submission OCR quiz')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna al modulo') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Corso: :course. Modulo: :module.', ['course' => $course->title, 'module' => $module->title]) }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                @if ($submissions->isEmpty())
                    <p class="text-sm text-base-content/70">{{ __('Nessuna correzione disponibile.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Utente') }}</th>
                                    <th>{{ __('Stato') }}</th>
                                    <th>{{ __('Caricato il') }}</th>
                                    <th>{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($submissions as $submission)
                                    <tr>
                                        <td>{{ $submission->user?->name ? $submission->user->name.' '.$submission->user->surname : __('Utente non rilevato') }}</td>
                                        <td>{{ $submission->status }}</td>
                                        <td>{{ $submission->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="flex flex-wrap gap-2">
                                            <a href="{{ route('admin.courses.modules.quiz.submissions.show', [$course, $module, $submission]) }}" class="btn btn-sm btn-outline">
                                                {{ __('Dettaglio') }}
                                            </a>
                                            @if ($submission->status === \App\Models\ModuleQuizSubmission::STATUS_NEEDS_REVIEW)
                                                <a href="{{ route('admin.courses.modules.quiz.submissions.review', [$course, $module, $submission]) }}" class="btn btn-sm btn-primary">
                                                    {{ __('Review') }}
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $submissions->links() }}
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
