<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Upload documenti quiz')">
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
                @if ($documentUploads->isEmpty())
                    <p class="text-sm text-base-content/70">{{ __('Nessun documento caricato.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Caricato da') }}</th>
                                    <th>{{ __('Stato') }}</th>
                                    <th>{{ __('Submission generate') }}</th>
                                    <th>{{ __('Caricato il') }}</th>
                                    <th>{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($documentUploads as $documentUpload)
                                    <tr>
                                        <td>{{ $documentUpload->uploadedBy?->name }} {{ $documentUpload->uploadedBy?->surname }}</td>
                                        <td>
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
                                        </td>
                                        <td>{{ $documentUpload->submissions_count ?? $documentUpload->submissions->count() }}</td>
                                        <td>{{ $documentUpload->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="flex flex-wrap gap-2">
                                            <a href="{{ route('admin.courses.modules.quiz.document-uploads.show', [$course, $module, $documentUpload]) }}" class="btn btn-sm btn-outline">
                                                {{ __('Dettaglio') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        {{ $documentUploads->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
