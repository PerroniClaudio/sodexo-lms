@props([
    'course',
    'courseValidator',
    'updateUrl',
])

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <form method="POST" action="{{ $updateUrl }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Allegati corso') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Carica copertina e locandina PDF del corso.') }}
                    </p>
                </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <div class="rounded-box border border-base-300 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold">{{ __('Immagine di copertina') }}</h3>
                            <p class="mt-1 text-sm text-base-content/70">{{ __('Formati immagine supportati.') }}</p>
                        </div>
                        @if ($course->cover_image_path)
                            <a href="{{ route('admin.courses.attachments.cover-image.preview', $course) }}" target="_blank" rel="noreferrer" class="btn btn-outline btn-sm">
                                <x-lucide-eye class="h-4 w-4" />
                                {{ __('Preview') }}
                            </a>
                        @endif
                    </div>
                    <div class="mt-4 space-y-3">
                        @if ($course->cover_image_path)
                            <p class="text-sm text-base-content/70">{{ __('File presente nel bucket.') }}</p>
                        @else
                            <p class="text-sm text-base-content/70">{{ __('Nessuna copertina caricata.') }}</p>
                        @endif
                        <input id="cover_image" name="cover_image" type="file" accept="image/*" class="file-input file-input-bordered w-full @error('cover_image') file-input-error @enderror">
                        @error('cover_image')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="rounded-box border border-base-300 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold">{{ __('Locandina PDF') }}</h3>
                            <p class="mt-1 text-sm text-base-content/70">{{ __('Carica locandina in formato PDF.') }}</p>
                        </div>
                        @if ($course->poster_pdf_path)
                            <a href="{{ route('admin.courses.attachments.poster-pdf.preview', $course) }}" target="_blank" rel="noreferrer" class="btn btn-outline btn-sm">
                                <x-lucide-eye class="h-4 w-4" />
                                {{ __('Preview') }}
                            </a>
                        @endif
                    </div>
                    <div class="mt-4 space-y-3">
                        @if ($course->poster_pdf_path)
                            <p class="text-sm text-base-content/70">{{ __('File presente nel bucket.') }}</p>
                        @else
                            <p class="text-sm text-base-content/70">{{ __('Nessuna locandina caricata.') }}</p>
                        @endif
                        <input id="poster_pdf" name="poster_pdf" type="file" accept="application/pdf,.pdf" class="file-input file-input-bordered w-full @error('poster_pdf') file-input-error @enderror">
                        @error('poster_pdf')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary" onclick="console.log('Button clicked', this.form); if (this.form) { console.log('Triggering submit...'); }">
                        <span>{{ __('Salva allegati') }}</span>
                        <x-lucide-save class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>