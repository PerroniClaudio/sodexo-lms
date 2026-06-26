<x-layouts.admin>
    @php
        $trainingPathEditSections = collect([
            ['key' => 'details', 'label' => __('Dati anagrafici percorso'), 'icon' => 'lucide-book-open-text'],
            ['key' => 'documents', 'label' => __('Documenti'), 'icon' => 'lucide-file-up'],
            ['key' => 'courses', 'label' => __('Corsi associati'), 'icon' => 'lucide-blocks'],
            ['key' => 'recipients', 'label' => __('Destinatari'), 'icon' => 'lucide-users'],
            ['key' => 'enrollments', 'label' => __('Iscritti'), 'icon' => 'lucide-user-plus'],
            ['key' => 'operations', 'label' => __('Operazioni percorso'), 'icon' => 'lucide-wrench'],
        ]);
        $activeSection = request('section', 'details');

        if (! $trainingPathEditSections->contains(fn (array $section): bool => $section['key'] === $activeSection)) {
            $activeSection = 'details';
        }

        $activeSectionConfig = $trainingPathEditSections->firstWhere('key', $activeSection);
        $selectedCourseIds = $trainingPath->courses->pluck('id')->map(fn ($id) => (string) $id);
        $selectedCourseOrderMap = $trainingPath->courses->mapWithKeys(fn ($course) => [
            (string) $course->getKey() => (int) $course->pivot->sort_order,
        ]);
        $initialCourseIds = $trainingPath->courses
            ->pluck('id')
            ->map(fn ($courseId) => (int) $courseId)
            ->values();
        $courseEnrollmentCleanupCounts = collect($courseEnrollmentCleanupCounts ?? [])
            ->mapWithKeys(fn ($count, $courseId) => [(string) $courseId => (int) $count]);
        $selectedCoursePayload = collect(old('course_ids', $trainingPath->courses->pluck('id')->all()))
            ->map(fn ($courseId) => (int) $courseId)
            ->unique()
            ->values()
            ->map(function (int $courseId) use ($availableCourses, $courseStatusLabels, $courseTypeLabels, $selectedCourseOrderMap): ?array {
                $course = $availableCourses->firstWhere('id', $courseId);

                if ($course === null) {
                    return null;
                }

                return [
                    'id' => $course->getKey(),
                    'title' => $course->title,
                    'code' => $course->code,
                    'type' => [
                        'key' => $course->type,
                        'label' => $courseTypeLabels[$course->type] ?? $course->type,
                    ],
                    'status' => [
                        'key' => $course->status,
                        'label' => $courseStatusLabels[$course->status] ?? $course->status,
                    ],
                    'year' => $course->year,
                    'sort_order' => (int) old('course_orders.'.$course->getKey(), $selectedCourseOrderMap->get((string) $course->getKey(), 1)),
                ];
            })
            ->filter()
            ->sortBy('sort_order')
            ->values();
        $selectedJobRoleIds = $trainingPath->jobRoles->pluck('id')->map(fn ($id) => (string) $id);
        $selectedJobTaskIds = $trainingPath->jobTasks->pluck('id')->map(fn ($id) => (string) $id);
        $selectedJobUnitIds = $trainingPath->jobUnits->pluck('id')->map(fn ($id) => (string) $id);
        $cannotAddTrainingPathEnrollmentMessage = $trainingPath->status !== 'published'
            ? __('Non puoi aggiungere iscritti a un percorso formativo non pubblicato')
            : null;
    @endphp

    <div
        class="min-h-screen max-w-full overflow-x-hidden bg-base-100"
        data-training-path-edit-page
        data-training-path-courses-api-url="{{ route('admin.api.training-paths.available-courses.index', $trainingPath) }}"
    >
        <div class="grid min-h-screen w-full min-w-0 grid-cols-1 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="min-w-0 border-b border-base-300 bg-base-200 p-4 lg:min-h-screen lg:border-b-0 lg:border-r">
                <div class="lg:sticky lg:top-4">
                    <details class="collapse collapse-arrow border border-base-300 bg-base-100 shadow-sm lg:hidden">
                        <summary class="collapse-title flex min-h-0 items-center gap-3 px-4 py-3 text-base font-medium">
                            <x-dynamic-component :component="$activeSectionConfig['icon']" class="h-5 w-5 shrink-0" />
                            <span class="min-w-0 truncate">{{ $activeSectionConfig['label'] }}</span>
                        </summary>
                        <div class="collapse-content px-2 pb-2">
                            <ul class="menu w-full gap-1">
                                @foreach ($trainingPathEditSections as $section)
                                    <li>
                                        <a
                                            href="{{ route('admin.training-paths.edit', $trainingPath).'?section='.$section['key'] }}"
                                            @class(['menu-active' => $activeSection === $section['key']])
                                        >
                                            <x-dynamic-component :component="$section['icon']" class="mr-2 inline-block h-5 w-5" />
                                            {{ $section['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </details>

                    <div class="hidden lg:block">
                        <ul class="menu w-full gap-1">
                            @foreach ($trainingPathEditSections as $section)
                                <li>
                                    <a
                                        href="{{ route('admin.training-paths.edit', $trainingPath).'?section='.$section['key'] }}"
                                        @class(['menu-active' => $activeSection === $section['key']])
                                    >
                                        <x-dynamic-component :component="$section['icon']" class="mr-2 inline-block h-5 w-5" />
                                        {{ $section['label'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </aside>

            <main class="min-w-0 overflow-hidden">
                <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                    <x-page-header :title="__('Modifica percorso formativo')" />

                    @if ($activeSection === 'details')
                        <div class="card border border-base-300 bg-base-100 shadow-sm">
                            <div class="card-body gap-6">
                                <div>
                                    <h2 class="card-title">{{ __('Dati anagrafici percorso') }}</h2>
                                    <p class="text-sm text-base-content/70">{{ __('Gestisci le informazioni principali del percorso formativo.') }}</p>
                                </div>

                                <form method="POST" action="{{ route('admin.training-paths.details.update', $trainingPath) }}" class="grid gap-6">
                                    @csrf
                                    @method('PUT')

                                    <div class="grid gap-6 md:grid-cols-2">
                                        <div class="form-control flex flex-col gap-2 md:col-span-2">
                                            <label for="title" class="label p-0">
                                                <span class="label-text font-medium">{{ __('Titolo del percorso formativo') }}</span>
                                            </label>
                                            <input
                                                id="title"
                                                name="title"
                                                type="text"
                                                value="{{ old('title', $trainingPath->title) }}"
                                                class="input input-bordered w-full @error('title') input-error @enderror"
                                                required
                                            >
                                            @error('title')
                                                <p class="text-sm text-error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="form-control flex flex-col gap-2 md:col-span-2">
                                            <label for="code" class="label p-0">
                                                <span class="label-text font-medium">{{ __('Codice percorso formativo') }}</span>
                                            </label>
                                            <input
                                                id="code"
                                                name="code"
                                                type="text"
                                                value="{{ old('code', $trainingPath->code) }}"
                                                class="input input-bordered w-full @error('code') input-error @enderror"
                                            >
                                            @error('code')
                                                <p class="text-sm text-error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="form-control flex flex-col gap-2 md:col-span-2">
                                            <label for="description" class="label p-0">
                                                <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                                            </label>
                                            <textarea
                                                id="description"
                                                name="description"
                                                class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
                                            >{{ old('description', $trainingPath->description) }}</textarea>
                                            @error('description')
                                                <p class="text-sm text-error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <div class="form-control flex flex-col gap-2 md:col-span-2">
                                            <label for="status" class="label p-0">
                                                <span class="label-text font-medium">{{ __('Stato') }}</span>
                                            </label>
                                            <select
                                                id="status"
                                                name="status"
                                                class="select select-bordered w-full @error('status') select-error @enderror"
                                            >
                                                @foreach ($trainingPathStatusLabels as $status => $label)
                                                    <option value="{{ $status }}" @selected(old('status', $trainingPath->status) === $status)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('status')
                                                <p class="text-sm text-error">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        <label class="form-control flex cursor-pointer flex-row items-start gap-3 md:col-span-2">
                                            <input
                                                type="checkbox"
                                                name="enforce_course_order"
                                                value="1"
                                                class="checkbox checkbox-primary mt-1"
                                                @checked(old('enforce_course_order', $trainingPath->enforce_course_order ?? true))
                                            >
                                            <span>
                                                <span class="block font-medium">{{ __('Ordine obbligato') }}</span>
                                                <span class="block text-sm text-base-content/70">{{ __('Se attivo, gli iscritti possono seguire i corsi solo nell\'ordine stabilito.') }}</span>
                                            </span>
                                        </label>
                                        @error('enforce_course_order')
                                            <p class="text-sm text-error md:col-span-2">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" class="btn btn-primary">
                                            <span>{{ __('Salva dati') }}</span>
                                            <x-lucide-save class="h-4 w-4" />
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($activeSection === 'documents')
                        <div class="card border border-base-300 bg-base-100 shadow-sm">
                            <div class="card-body gap-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h2 class="card-title">{{ __('Documenti') }}</h2>
                                        <p class="text-sm text-base-content/70">{{ __('Gestisci i documenti collegati al percorso formativo.') }}</p>
                                    </div>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <a href="{{ route('admin.training-paths.program.download', $trainingPath) }}" class="btn btn-outline">
                                            <x-lucide-download class="h-4 w-4" />
                                            <span>{{ __('Scarica programma del percorso formativo') }}</span>
                                        </a>
                                        <button type="button" class="btn btn-primary" onclick="training_path_document_upload_modal.showModal()">
                                            <x-lucide-upload class="h-4 w-4" />
                                            <span>{{ __('Carica documento') }}</span>
                                        </button>
                                    </div>
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
                                            @forelse ($trainingPath->documents as $document)
                                                <tr>
                                                    <td class="font-medium">{{ $document->file_name }}</td>
                                                    <td>{{ $trainingPathDocumentFileTypeLabels[$document->file_type] ?? $document->file_type }}</td>
                                                    <td>{{ $trainingPathDocumentCategoryLabels[$document->category] ?? $document->category }}</td>
                                                    <td>
                                                        <div class="flex justify-end gap-2">
                                                            <a href="{{ route('admin.training-paths.documents.download', [$trainingPath, $document]) }}" class="btn btn-ghost btn-sm" title="{{ __('Download') }}">
                                                                <x-lucide-download class="h-4 w-4" />
                                                            </a>
                                                            <form method="POST" action="{{ route('admin.training-paths.documents.destroy', [$trainingPath, $document]) }}" onsubmit="return confirm('{{ __('Eliminare questo documento?') }}')">
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

                        <dialog id="training_path_document_upload_modal" class="modal">
                            <div class="modal-box max-w-2xl">
                                <form method="dialog">
                                    <button class="btn btn-circle btn-ghost btn-sm absolute right-2 top-2">×</button>
                                </form>
                                <h3 class="text-lg font-semibold">{{ __('Carica documento') }}</h3>
                                <form method="POST" action="{{ route('admin.training-paths.documents.store', $trainingPath) }}" enctype="multipart/form-data" class="mt-6 grid gap-4">
                                    @csrf
                                    <div class="form-control flex flex-col gap-2">
                                        <label for="training_path_document_file_name" class="label p-0">
                                            <span class="label-text font-medium">{{ __('Nome file') }}</span>
                                        </label>
                                        <input
                                            id="training_path_document_file_name"
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
                                            <label for="training_path_document_file_type" class="label p-0">
                                                <span class="label-text font-medium">{{ __('Tipo file') }}</span>
                                            </label>
                                            <select
                                                id="training_path_document_file_type"
                                                name="file_type"
                                                class="select select-bordered w-full @error('file_type') select-error @enderror"
                                                required
                                            >
                                                @foreach ($trainingPathDocumentFileTypeLabels as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('file_type', 'document') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-control flex flex-col gap-2">
                                            <label for="training_path_document_category" class="label p-0">
                                                <span class="label-text font-medium">{{ __('Categoria') }}</span>
                                            </label>
                                            <select
                                                id="training_path_document_category"
                                                name="category"
                                                class="select select-bordered w-full @error('category') select-error @enderror"
                                                required
                                            >
                                                @foreach ($trainingPathDocumentCategoryLabels as $value => $label)
                                                    <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-control flex flex-col gap-2">
                                        <label for="training_path_document_file" class="label p-0">
                                            <span class="label-text font-medium">{{ __('File') }}</span>
                                        </label>
                                        <input
                                            id="training_path_document_file"
                                            type="file"
                                            name="file"
                                            accept="application/pdf,.pdf"
                                            class="file-input file-input-bordered w-full @error('file') file-input-error @enderror"
                                            required
                                        >
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
                    @endif

                    @if ($activeSection === 'courses')
                        <div class="card border border-base-300 bg-base-100 shadow-sm">
                            <div class="card-body gap-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 class="card-title">{{ __('Corsi associati') }}</h2>
                                        <p class="text-sm text-base-content/70">{{ __('Aggiungi i corsi dal catalogo e riordina quelli selezionati con drag and drop.') }}</p>
                                    </div>

                                    <button type="button" class="btn btn-primary" data-open-training-path-courses-modal>
                                        <span>{{ __('Aggiungi corsi') }}</span>
                                        <x-lucide-plus class="h-4 w-4" />
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('admin.training-paths.courses.update', $trainingPath) }}" class="grid gap-6" data-training-path-courses-form>
                                    @csrf
                                    @method('PUT')

                                    <div data-training-path-selected-courses-panel>
                                        <div class="hidden space-y-4 rounded-box border border-base-300 bg-base-100 p-4" data-training-path-selected-courses-wrapper>
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <h3 class="text-sm font-semibold">{{ __('Corsi selezionati') }}</h3>
                                                    <p class="text-sm text-base-content/70">{{ __('Trascina per cambiare ordine di erogazione nel percorso.') }}</p>
                                                </div>
                                                <span class="badge badge-outline h-fit" data-training-path-selected-courses-count></span>
                                            </div>

                                            <div class="grid gap-3" data-training-path-selected-courses-sortable></div>
                                        </div>

                                        <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70" data-training-path-selected-courses-empty>
                                            {{ __('Nessun corso associato al percorso.') }}
                                        </div>
                                    </div>

                                    <div data-training-path-course-hidden-inputs></div>

                                    @foreach (['course_ids', 'course_ids.*', 'course_orders', 'course_orders.*'] as $errorKey)
                                        @error($errorKey)
                                            <p class="text-sm text-error">{{ $message }}</p>
                                        @enderror
                                    @endforeach

                                    <dialog class="modal" data-training-path-courses-modal>
                                        <div class="modal-box max-w-6xl">
                                            <div class="space-y-2">
                                                <h3 class="text-lg font-semibold">{{ __('Aggiungi corsi al percorso') }}</h3>
                                                <p class="text-sm text-base-content/70">{{ __('Seleziona i corsi dal catalogo e conferma per associarli al percorso.') }}</p>
                                            </div>

                                            <div class="mt-6 rounded-box border border-base-300 bg-base-200/30 p-4">
                                                <div class="flex items-center justify-between gap-3">
                                                    <h4 class="text-sm font-semibold">{{ __('Corsi selezionati') }}</h4>
                                                    <span class="badge badge-outline h-fit" data-training-path-modal-selected-courses-count></span>
                                                </div>

                                                <div class="mt-4 flex flex-wrap gap-2" data-training-path-selected-course-chips></div>
                                                <div class="mt-4 rounded-box border border-dashed border-base-300 bg-base-100/70 p-4 text-sm text-base-content/70" data-training-path-modal-selected-courses-empty>
                                                    {{ __('Nessun corso selezionato.') }}
                                                </div>
                                            </div>

                                            <div class="mt-6 flex w-full max-w-xl items-center gap-2">
                                                <label class="input input-bordered flex w-full items-center gap-2">
                                                    <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                                    <input
                                                        type="search"
                                                        class="grow"
                                                        data-training-path-courses-search
                                                        placeholder="{{ __('Cerca nei corsi') }}"
                                                    >
                                                </label>
                                                <button type="button" class="btn btn-primary" data-training-path-courses-search-button>
                                                    {{ __('Cerca') }}
                                                </button>
                                            </div>

                                            <div class="relative mt-4" data-training-path-courses-table-container>
                                                <div class="pointer-events-none absolute inset-0 z-10 hidden items-center justify-center bg-base-100/70" data-training-path-courses-loader>
                                                    <span class="loading loading-spinner loading-md"></span>
                                                </div>

                                                <div class="overflow-x-auto rounded-box border border-base-300">
                                                    <table class="table table-zebra w-full">
                                                        <thead>
                                                            <tr>
                                                                <th><button type="button" class="inline-flex items-center gap-2" data-training-path-courses-sort-key="id">{{ __('ID') }}</button></th>
                                                                <th><button type="button" class="inline-flex items-center gap-2" data-training-path-courses-sort-key="title">{{ __('Titolo del corso') }}</button></th>
                                                                <th><button type="button" class="inline-flex items-center gap-2" data-training-path-courses-sort-key="type">{{ __('Tipologia') }}</button></th>
                                                                <th><button type="button" class="inline-flex items-center gap-2" data-training-path-courses-sort-key="status">{{ __('Stato') }}</button></th>
                                                                <th><button type="button" class="inline-flex items-center gap-2" data-training-path-courses-sort-key="year">{{ __('Anno del corso') }}</button></th>
                                                                <th>{{ __('Azioni') }}</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody data-training-path-courses-tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <div class="mt-4 hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70" data-training-path-courses-empty>
                                                {{ __('Nessun corso disponibile.') }}
                                            </div>

                                            <div class="mt-4 flex flex-col gap-3 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
                                                <p data-training-path-courses-summary></p>
                                                <div class="join" data-training-path-courses-pagination></div>
                                            </div>

                                            <template data-training-path-course-table-row-template>
                                                <tr class="hover:bg-base-200">
                                                    <td data-cell="id"></td>
                                                    <td class="font-medium" data-cell="title"></td>
                                                    <td data-cell="type"></td>
                                                    <td><span class="badge badge-outline h-fit" data-cell="status"></span></td>
                                                    <td data-cell="year"></td>
                                                    <td><button type="button" class="btn btn-primary btn-sm" data-action="toggle-course"></button></td>
                                                </tr>
                                            </template>

                                            <template data-training-path-selected-course-chip-template>
                                                <button type="button" class="btn btn-sm btn-outline gap-2" data-action="remove-chip">
                                                    <span data-chip-label></span>
                                                    <x-lucide-x class="h-3.5 w-3.5" />
                                                </button>
                                            </template>

                                            <template data-training-path-selected-course-item-template>
                                                <div
                                                    class="rounded-box border border-base-300 bg-base-100 p-4 transition-shadow"
                                                    draggable="true"
                                                    data-training-path-selected-course-item
                                                >
                                                    <div class="flex gap-3">
                                                        <div class="mt-0.5 flex h-9 w-9 shrink-0 cursor-move items-center justify-center rounded-full border border-base-300 text-base-content/60">
                                                            <x-lucide-move class="h-4 w-4" />
                                                        </div>
                                                        <div class="flex min-w-0 flex-1 flex-col gap-3">
                                                            <div class="min-w-0">
                                                                <p class="wrap-break-word text-sm font-semibold text-base-content" data-item-title></p>
                                                                <p class="text-sm text-base-content/70" data-item-meta></p>
                                                            </div>
                                                            <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
                                                                <span class="badge badge-sm badge-outline h-fit" data-item-type></span>
                                                                <span class="badge badge-sm badge-outline h-fit" data-item-status></span>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="btn btn-ghost btn-sm text-error" data-action="remove-selected-course">
                                                            <x-lucide-x class="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>

                                            <script type="application/json" data-training-path-selected-courses>
                                                {!! json_encode($selectedCoursePayload->values()->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                                            </script>

                                            <script type="application/json" data-training-path-initial-course-ids>
                                                {!! json_encode($initialCourseIds->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                                            </script>

                                            <script type="application/json" data-training-path-course-enrollment-cleanup-counts>
                                                {!! json_encode($courseEnrollmentCleanupCounts->all(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                                            </script>

                                            <div class="modal-action mt-6">
                                                <button type="button" class="btn btn-ghost" data-close-training-path-courses-modal>
                                                    {{ __('Annulla') }}
                                                </button>
                                                <button type="button" class="btn btn-primary" data-confirm-training-path-courses>
                                                    <span>{{ __('Conferma selezione') }}</span>
                                                    <x-lucide-check class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>

                                        {{-- <form method="dialog" class="modal-backdrop">
                                            <button type="submit">{{ __('Close') }}</button>
                                        </form> --}}
                                    </dialog>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($activeSection === 'recipients')
                        <div class="card border border-base-300 bg-base-100 shadow-sm">
                            <div class="card-body gap-6">
                                <div>
                                    <h2 class="card-title">{{ __('Destinatari') }}</h2>
                                    <p class="text-sm text-base-content/70">{{ __('Limita la visibilità del percorso in base ai dati lavorativi degli utenti iscritti.') }}</p>
                                </div>

                                <form method="POST" action="{{ route('admin.training-paths.recipients.update', $trainingPath) }}" class="flex flex-col gap-6" data-training-path-recipients-form>
                                    @csrf
                                    @method('PUT')

                                    <label class="flex items-start gap-3 rounded-box border border-base-300 bg-base-200/40 p-4">
                                        <input
                                            type="checkbox"
                                            name="visible_to_all"
                                            value="1"
                                            class="checkbox checkbox-primary mt-1"
                                            data-auto-submit
                                            @checked(old('visible_to_all', $trainingPath->visible_to_all))
                                        >
                                        <span>
                                            <span class="block font-medium">{{ __('Visibile a tutti') }}</span>
                                            <span class="block text-sm text-base-content/70">{{ __('Se attivo, le selezioni sotto non limitano il percorso.') }}</span>
                                        </span>
                                    </label>

                                    <x-admin.course.edit.sections.recipient-table
                                        :items="$jobRoles"
                                        :selected-ids="$selectedJobRoleIds"
                                        input-name="job_role_ids"
                                        :title="__('Ruoli')"
                                        :empty-message="__('Nessun ruolo disponibile.')"
                                    />

                                    <x-admin.course.edit.sections.recipient-table
                                        :items="$jobTasks"
                                        :selected-ids="$selectedJobTaskIds"
                                        input-name="job_task_ids"
                                        :title="__('Mansioni')"
                                        :empty-message="__('Nessuna mansione disponibile.')"
                                    />

                                    <x-admin.course.edit.sections.recipient-table
                                        :items="$jobUnits"
                                        :selected-ids="$selectedJobUnitIds"
                                        input-name="job_unit_ids"
                                        :title="__('Unità produttive')"
                                        :empty-message="__('Nessuna unità produttiva disponibile.')"
                                    />

                                    @foreach (['visible_to_all', 'job_role_ids', 'job_role_ids.*', 'job_task_ids', 'job_task_ids.*', 'job_unit_ids', 'job_unit_ids.*'] as $errorKey)
                                        @error($errorKey)
                                            <p class="text-sm text-error">{{ $message }}</p>
                                        @enderror
                                    @endforeach

                                    <p class="hidden text-sm" data-training-path-recipients-status></p>

                                    <div class="flex justify-end">
                                        <button type="submit" class="btn btn-primary">
                                            <span>{{ __('Salva') }}</span>
                                            <x-lucide-save class="h-4 w-4" />
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($activeSection === 'enrollments')
                        <div
                            class="card border border-base-300 bg-base-100 shadow-sm"
                            data-training-path-enrollments-table
                            data-training-path-enrollments-api-url="{{ route('admin.api.training-paths.enrollments.index', $trainingPath) }}"
                            data-training-path-enrollments-search-users-api-url="{{ route('admin.api.training-paths.enrollments.search-users', $trainingPath) }}"
                            data-training-path-enrollments-store-api-url="{{ route('admin.api.training-paths.enrollments.store', $trainingPath) }}"
                        >
                            <div class="card-body gap-6">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 class="card-title">{{ __('Iscritti') }}</h2>
                                        <p class="text-sm text-base-content/70">{{ __('Gestisci gli iscritti al percorso. L’avanzamento usa i corsi del percorso completati rispetto al totale.') }}</p>
                                    </div>

                                    <span
                                        @class([
                                            'inline-flex',
                                            'tooltip tooltip-left' => $cannotAddTrainingPathEnrollmentMessage !== null,
                                        ])
                                        @if ($cannotAddTrainingPathEnrollmentMessage !== null)
                                            data-tip="{{ $cannotAddTrainingPathEnrollmentMessage }}"
                                        @endif
                                    >
                                        <button
                                            type="button"
                                            class="btn btn-primary"
                                            data-open-training-path-enrollment-modal
                                            @disabled($cannotAddTrainingPathEnrollmentMessage !== null)
                                        >
                                            <span>{{ __('Nuovo iscritto') }}</span>
                                            <x-lucide-plus class="h-4 w-4" />
                                        </button>
                                    </span>
                                </div>

                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="label cursor-pointer justify-start gap-3 p-0">
                                        <input type="checkbox" class="checkbox" data-training-path-enrollments-show-trashed>
                                        <span class="label-text">{{ __('Mostra eliminati') }}</span>
                                    </label>

                                    <div class="flex w-full max-w-xl items-center gap-2">
                                        <label class="input input-bordered flex w-full items-center gap-2">
                                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                            <input
                                                type="search"
                                                class="grow"
                                                data-training-path-enrollments-search
                                                placeholder="{{ __('Cerca nome, cognome, CF, email') }}"
                                            >
                                        </label>
                                        <button type="button" class="btn btn-primary" data-training-path-enrollments-search-button>
                                            {{ __('Cerca') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="relative" data-training-path-enrollments-table-container>
                                    <div class="pointer-events-none absolute inset-0 z-10 hidden items-center justify-center bg-base-100/70" data-training-path-enrollments-loader>
                                        <span class="loading loading-spinner loading-md"></span>
                                    </div>

                                    <div class="overflow-x-auto rounded-box border border-base-300">
                                        <table class="table table-zebra w-full">
                                            <thead>
                                                <tr>
                                                    <th><button type="button" class="inline-flex items-center gap-2" data-training-path-sort-key="surname">{{ __('Cognome') }}</button></th>
                                                    <th><button type="button" class="inline-flex items-center gap-2" data-training-path-sort-key="name">{{ __('Nome') }}</button></th>
                                                    <th><button type="button" class="inline-flex items-center gap-2" data-training-path-sort-key="fiscal_code">{{ __('CF') }}</button></th>
                                                    <th><button type="button" class="inline-flex items-center gap-2" data-training-path-sort-key="email">{{ __('Email') }}</button></th>
                                                    <th>{{ __('Stato') }}</th>
                                                    <th>{{ __('Avanzamento') }}</th>
                                                    <th><button type="button" class="inline-flex items-center gap-2" data-training-path-sort-key="assigned_at">{{ __('Assegnato il') }}</button></th>
                                                    <th class="sticky right-0 z-20 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">{{ __('Azioni') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody data-training-path-enrollments-tbody></tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70" data-training-path-enrollments-empty>
                                    {{ __('Nessun iscritto presente per questo percorso.') }}
                                </div>

                                <div class="flex flex-col gap-3 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
                                    <p data-training-path-enrollments-summary></p>
                                    <div class="join" data-training-path-enrollments-pagination></div>
                                </div>

                                <template data-training-path-enrollment-row-template>
                                    <tr class="hover:bg-base-200">
                                        <td data-cell="surname"></td>
                                        <td data-cell="name"></td>
                                        <td data-cell="fiscal_code"></td>
                                        <td data-cell="email"></td>
                                        <td><span class="badge badge-outline h-fit" data-cell="status"></span></td>
                                        <td data-cell="progress"></td>
                                        <td data-cell="assigned_at"></td>
                                        <td class="sticky right-0 z-10 bg-base-100 shadow-[-8px_0_12px_-10px_rgba(15,23,42,0.35)]">
                                            <div class="flex flex-col gap-2 xl:flex-row">
                                                <button type="button" class="btn btn-xs btn-error xl:btn-sm" data-action="delete">{{ __('Elimina') }}</button>
                                                <button type="button" class="btn btn-xs btn-success xl:btn-sm" data-action="restore">{{ __('Ripristina') }}</button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <dialog class="modal" data-create-training-path-enrollment-modal>
                                    <div class="modal-box max-w-3xl">
                                        <div class="space-y-2">
                                            <h3 class="text-lg font-semibold">{{ __('Nuovo iscritto') }}</h3>
                                            <p class="text-sm text-base-content/70">{{ __('Cerca un utente e selezionalo per iscriverlo al percorso formativo.') }}</p>
                                        </div>

                                        <div class="mt-6 flex items-center gap-2">
                                            <label class="input input-bordered flex w-full items-center gap-2">
                                                <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                                                <input type="search" class="grow" data-training-path-enrollment-user-search placeholder="{{ __('Cerca nome, cognome, CF, email o ID utente') }}">
                                            </label>
                                            <button type="button" class="btn btn-primary" data-training-path-enrollment-user-search-button>{{ __('Cerca') }}</button>
                                        </div>

                                        <div class="mt-4 overflow-x-auto rounded-box border border-base-300">
                                            <table class="table table-zebra w-full">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>{{ __('Cognome') }}</th>
                                                        <th>{{ __('Nome') }}</th>
                                                        <th>{{ __('CF') }}</th>
                                                        <th>{{ __('Email') }}</th>
                                                        <th>{{ __('Azioni') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody data-training-path-enrollment-user-results></tbody>
                                            </table>
                                        </div>

                                        <div class="mt-4 hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-center text-sm text-base-content/70" data-training-path-enrollment-user-results-empty>
                                            {{ __('Nessun utente trovato.') }}
                                        </div>

                                        <template data-training-path-enrollment-user-row-template>
                                            <tr class="hover:bg-base-200">
                                                <td data-cell="id"></td>
                                                <td data-cell="surname"></td>
                                                <td data-cell="name"></td>
                                                <td data-cell="fiscal_code"></td>
                                                <td data-cell="email"></td>
                                                <td><button type="button" class="btn btn-primary btn-sm" data-action="select-user">{{ __('Seleziona') }}</button></td>
                                            </tr>
                                        </template>

                                        <div class="modal-action mt-6">
                                            <button type="button" class="btn btn-ghost" data-close-create-training-path-enrollment-modal>{{ __('Chiudi') }}</button>
                                        </div>
                                    </div>
                                    <form method="dialog" class="modal-backdrop">
                                        <button type="submit">{{ __('Close') }}</button>
                                    </form>
                                </dialog>

                                <dialog class="modal" data-confirm-training-path-enrollment-modal>
                                    <div class="modal-box max-w-lg">
                                        <div class="space-y-2">
                                            <h3 class="text-lg font-semibold">{{ __('Conferma iscrizione') }}</h3>
                                            <p class="text-sm text-base-content/70" data-confirm-training-path-enrollment-message></p>
                                        </div>

                                        <div class="modal-action mt-6">
                                            <form method="dialog">
                                                <button type="submit" class="btn btn-ghost">{{ __('Annulla') }}</button>
                                            </form>
                                            <button type="button" class="btn btn-primary" data-confirm-training-path-enrollment-submit data-loading-text="{{ __('Salvataggio...') }}">
                                                {{ __('Conferma') }}
                                            </button>
                                        </div>
                                    </div>
                                    <form method="dialog" class="modal-backdrop">
                                        <button type="submit">{{ __('Close') }}</button>
                                    </form>
                                </dialog>
                            </div>
                        </div>
                    @endif

                    @if ($activeSection === 'operations')
                        <div class="card border border-base-300 bg-base-100 shadow-sm">
                            <div class="card-body gap-6">
                                <div>
                                    <h2 class="card-title">{{ __('Operazioni percorso') }}</h2>
                                    <p class="text-sm text-base-content/70">{{ __('Elimina il percorso formativo se non serve più.') }}</p>
                                </div>

                                <form method="POST" action="{{ route('admin.training-paths.destroy', $trainingPath) }}" onsubmit="return confirm('{{ __('Eliminare questo percorso formativo?') }}')">
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn btn-error">
                                        <x-lucide-trash-2 class="h-4 w-4" />
                                        <span>{{ __('Elimina percorso formativo') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </main>
        </div>
    </div>

    @vite('resources/js/pages/admin-training-path-edit.js')
</x-layouts.admin>
