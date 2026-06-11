@props([
    'course',
    'courseValidator',
])

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-4">
            <div>
                <h2 class="card-title">{{ __('Tutor') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Sezione riservata alla futura gestione dei tutor del corso.') }}
                </p>
            </div>

            <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-6 text-sm text-base-content/70">
                {{ __('Funzione in sviluppo. Per ora non sono previste azioni in questa sezione.') }}
            </div>
        </div>
    </div>
</div>