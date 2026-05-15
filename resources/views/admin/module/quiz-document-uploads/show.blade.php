<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Dettaglio upload documento')">
            <x-slot:actions>
                <a href="{{ route('admin.courses.modules.quiz.document-uploads.index', [$course, $module]) }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna alla lista') }}</span>
                </a>
            </x-slot:actions>

            {{ __('Corso: :course. Modulo: :module.', ['course' => $course->title, 'module' => $module->title]) }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <h2 class="text-lg font-semibold">{{ __('Informazioni documento') }}</h2>
                <dl class="grid gap-2 text-sm">
                    <div>
                        <dt class="font-medium">{{ __('Stato') }}</dt>
                        <dd>
                            @if ($documentUpload->status === \App\Models\ModuleQuizDocumentUpload::STATUS_UPLOADED)
                                <span class="badge badge-info">{{ __('Caricato') }}</span>
                            @elseif ($documentUpload->status === \App\Models\ModuleQuizDocumentUpload::STATUS_PROCESSING)
                                <span class="badge badge-warning">{{ __('In elaborazione') }}</span>
                            @elseif ($documentUpload->status === \App\Models\ModuleQuizDocumentUpload::STATUS_PROCESSED)
                                <span class="badge badge-success">{{ __('Processato') }}</span>
                            @elseif ($documentUpload->status === \App\Models\ModuleQuizDocumentUpload::STATUS_FAILED)
                                <span class="badge badge-error">{{ __('Fallito') }}</span>
                            @else
                                <span class="badge badge-ghost">{{ $documentUpload->status }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium">{{ __('Caricato da') }}</dt>
                        <dd>{{ $documentUpload->uploadedBy?->name }} {{ $documentUpload->uploadedBy?->surname }} ({{ $documentUpload->uploadedBy?->email }})</dd>
                    </div>
                    <div>
                        <dt class="font-medium">{{ __('Caricato il') }}</dt>
                        <dd>{{ $documentUpload->created_at?->format('d/m/Y H:i:s') }}</dd>
                    </div>
                    @if ($documentUpload->processed_at)
                        <div>
                            <dt class="font-medium">{{ __('Processato il') }}</dt>
                            <dd>{{ $documentUpload->processed_at?->format('d/m/Y H:i:s') }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="font-medium">{{ __('File') }}</dt>
                        <dd>{{ $documentUpload->path }}</dd>
                    </div>
                    @if ($documentUpload->error_message)
                        <div>
                            <dt class="font-medium text-error">{{ __('Errore') }}</dt>
                            <dd class="text-error">{{ $documentUpload->error_message }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-4">
                <h2 class="text-lg font-semibold">{{ __('Submission generate') }}</h2>

                @if ($documentUpload->submissions->isEmpty())
                    <p class="text-sm text-base-content/70">{{ __('Nessuna submission generata.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Utente') }}</th>
                                    <th>{{ __('Stato') }}</th>
                                    <th>{{ __('Punteggio') }}</th>
                                    <th>{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($documentUpload->submissions as $submission)
                                    <tr>
                                        <td>{{ $submission->user?->name }} {{ $submission->user?->surname }}</td>
                                        <td>
                                            @if ($submission->status === \App\Models\ModuleQuizSubmission::STATUS_NEEDS_REVIEW)
                                                <span class="badge badge-warning">{{ __('Da revisionare') }}</span>
                                            @elseif ($submission->status === \App\Models\ModuleQuizSubmission::STATUS_FINALIZED)
                                                <span class="badge badge-success">{{ __('Finalizzato') }}</span>
                                            @else
                                                <span class="badge badge-ghost">{{ __(ucfirst($submission->status)) }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($submission->score !== null)
                                                {{ $submission->score }}/{{ $submission->total_score }}
                                            @else
                                                -
                                            @endif
                                        </td>
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
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
