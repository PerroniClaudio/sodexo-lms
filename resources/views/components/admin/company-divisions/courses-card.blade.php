@props(['companyDivision', 'courses'])

@php
    $selectedCourseIds = collect(old('course_ids', $companyDivision->courses()->pluck('courses.id')->all()))->map(fn ($id) => (int) $id);
@endphp

<form method="POST" action="{{ route('admin.company-divisions.update', $companyDivision) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="name" value="{{ $companyDivision->name }}">
    <input type="hidden" name="vat_number" value="{{ $companyDivision->vat_number }}">
    <input type="hidden" name="sync_courses" value="1">

    <section class="card border border-base-300 bg-base-100 shadow-sm" data-association-card="courses">
        <div class="card-body gap-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="card-title">{{ __('Corsi associati') }}</h2>
                    <p class="text-sm text-base-content/70">{{ __('Corsi visibili nella divisione.') }}</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="company_division_courses_modal.showModal()">
                    <x-lucide-plus class="h-4 w-4" />
                    <span>{{ __('Aggiungi') }}</span>
                </button>
            </div>

            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Codice') }}</th>
                            <th>{{ __('Corso') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($courses as $course)
                            <tr data-selected-row="course-{{ $course->getKey() }}" @class(['hidden' => ! $selectedCourseIds->contains((int) $course->getKey())])>
                                <td>{{ $course->code ?? '-' }}</td>
                                <td>{{ $course->title }}</td>
                            </tr>
                        @endforeach
                        <tr data-empty-row="courses" @class(['hidden' => $selectedCourseIds->isNotEmpty()])>
                            <td colspan="2" class="text-center text-base-content/60">{{ __('Nessun corso associato.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    <dialog id="company_division_courses_modal" class="modal">
        <div class="modal-box max-w-5xl">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-lg font-semibold">{{ __('Aggiungi corsi') }}</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" aria-label="{{ __('Chiudi') }}" onclick="company_division_courses_modal.close()">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>
            <label class="input input-bordered mt-4 flex items-center gap-2">
                <x-lucide-search class="h-4 w-4 text-base-content/60" />
                <input type="search" class="grow" placeholder="{{ __('Cerca corsi') }}" data-association-search="courses">
            </label>
            <div class="mt-4 max-h-[28rem] overflow-auto rounded-box border border-base-300">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th></th>
                            <th>{{ __('Codice') }}</th>
                            <th>{{ __('Corso') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($courses as $course)
                            <tr data-searchable-row="courses" data-search-text="{{ \Illuminate\Support\Str::lower(($course->code ?? '').' '.$course->title) }}">
                                <td>
                                    <input type="checkbox" name="course_ids[]" value="{{ $course->getKey() }}" class="checkbox checkbox-primary" @checked($selectedCourseIds->contains((int) $course->getKey()))>
                                </td>
                                <td>{{ $course->code ?? '-' }}</td>
                                <td>{{ $course->title }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-action">
                <button type="submit" class="btn btn-primary">{{ __('Conferma') }}</button>
            </div>
        </div>
    </dialog>
</form>
