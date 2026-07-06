@props([
    'categoryLabels',
    'course',
    'courseValidator',
    'fileTypeLabels',
    'storeUrl',
])

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="card-title">{{ __('Fascicolo Corso') }}</h2>
                    <p class="text-sm text-base-content/70">{{ __('Gestisci i documenti collegati al corso.') }}</p>
                </div>
                <button type="button" class="btn btn-primary" onclick="course_document_upload_modal.showModal()">
                    <x-lucide-upload class="h-4 w-4" />
                    <span>{{ __('Carica documento') }}</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Nome file') }}</th>
                            <th>{{ __('Tipo') }}</th>
                            <th>{{ __('Categoria') }}</th>
                            <th class="text-right">{{ __('Azioni') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($course->documents as $document)
                            <tr>
                                <td class="font-medium">{{ $document->file_name }}</td>
                                <td>{{ $fileTypeLabels[$document->file_type] ?? $document->file_type }}</td>
                                <td>{{ $categoryLabels[$document->category] ?? $document->category }}</td>
                                <td>
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('admin.courses.documents.download', [$course, $document]) }}" class="btn btn-ghost btn-sm" title="{{ __('Download') }}">
                                            <x-lucide-download class="h-4 w-4" />
                                        </a>
                                        <form method="POST" action="{{ route('admin.courses.documents.destroy', [$course, $document]) }}" onsubmit="return confirm('{{ __('Eliminare questo documento?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm text-error" title="{{ __('Elimina') }}">
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-8 text-center text-sm text-base-content/70">
                                    {{ __('Nessun documento caricato.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <dialog id="course_document_upload_modal" class="modal">
        <div class="modal-box max-w-2xl">
            <form method="dialog">
                <button class="btn btn-circle btn-ghost btn-sm absolute right-2 top-2">✕</button>
            </form>
            <h3 class="text-lg font-semibold">{{ __('Carica documento') }}</h3>
            <form method="POST" action="{{ $storeUrl }}" enctype="multipart/form-data" class="mt-6 grid gap-4">
                @csrf
                <div class="form-control flex flex-col gap-2">
                    <label for="course_document_file_name" class="label p-0">
                        <span class="label-text font-medium">{{ __('Nome file') }}</span>
                    </label>
                    <input
                        id="course_document_file_name"
                        type="text"
                        name="file_name"
                        value="{{ old('file_name') }}"
                        class="input input-bordered w-full @error('file_name') input-error @enderror"
                        required
                    >
                    @error('file_name')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="form-control flex flex-col gap-2">
                        <label for="course_document_file_type" class="label p-0">
                            <span class="label-text font-medium">{{ __('Tipo file') }}</span>
                        </label>
                        <select
                            id="course_document_file_type"
                            name="file_type"
                            class="select select-bordered w-full @error('file_type') select-error @enderror"
                            required
                        >
                            @foreach ($fileTypeLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('file_type', 'document') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('file_type')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label for="course_document_category" class="label p-0">
                            <span class="label-text font-medium">{{ __('Categoria') }}</span>
                        </label>
                        <select
                            id="course_document_category"
                            name="category"
                            class="select select-bordered w-full @error('category') select-error @enderror"
                            required
                        >
                            @foreach ($categoryLabels as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="form-control flex flex-col gap-2">
                    <label for="course_document_file" class="label p-0">
                        <span class="label-text font-medium">{{ __('File') }}</span>
                    </label>
                    <input
                        id="course_document_file"
                        type="file"
                        name="file"
                        accept="application/pdf,.pdf"
                        class="file-input file-input-bordered w-full @error('file') file-input-error @enderror"
                        required
                    >
                    @error('file')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="modal-action">
                    <button type="submit" class="btn btn-primary">
                        <x-lucide-upload class="h-4 w-4" />
                        <span>{{ __('Carica documento') }}</span>
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Chiudi') }}</button>
        </form>
    </dialog>
</div>
