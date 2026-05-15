<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="$sourceType === 'upload' ? __('Submission OCR quiz') : __('Submission quiz')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.edit', [$course, $module]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna al modulo') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Corso: :course. Modulo: :module.', ['course' => $course->title, 'module' => $module->title]) }}
        </x-page-header>

        @if ($sourceType === 'upload')
            <div class="flex justify-end">
                <a href="{{ route('admin.courses.modules.quiz.submissions.index', [$course, $module]) }}" class="btn btn-outline btn-sm">
                    <span>{{ __('Vedi tutte le submission') }}</span>
                    <x-lucide-external-link class="h-4 w-4" />
                </a>
            </div>
        @endif

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                @if ($submissions->isEmpty())
                    <p class="text-sm text-base-content/70">{{ __('Nessuna submission disponibile.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Utente') }}</th>
                                    <th>{{ __('Modalità') }}</th>
                                    <th>{{ __('Stato') }}</th>
                                    <th>{{ __('Caricato il') }}</th>
                                    <th>{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($submissions as $submission)
                                    <tr>
                                        <td>{{ $submission->user?->name ? $submission->user->name.' '.$submission->user->surname : __('Utente non rilevato') }}</td>
                                        <td>
                                            @if ($submission->source_type === 'online')
                                                <span class="badge badge-primary">{{ __('Online') }}</span>
                                            @elseif ($submission->source_type === 'upload')
                                                <span class="badge badge-secondary">{{ __('Upload') }}</span>
                                            @else
                                                <span class="badge badge-ghost">{{ $submission->source_type }}</span>
                                            @endif
                                        </td>
                                        <td>{{ __(ucfirst($submission->status)) }}</td>
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
