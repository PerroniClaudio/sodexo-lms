@props(['data' => []])

@php
    extract($data);
@endphp

<h3 class="text-lg font-semibold" data-course-class-modal-title>{{ __('Nuova classe') }}</h3>
<form class="mt-6 space-y-6" data-course-class-form>
    <input type="hidden" name="module_id" value="{{ $module->getKey() }}">

    <div class="form-control flex flex-col gap-2">
        <label class="label p-0">
            <span class="label-text font-medium">{{ __('Modulo') }}</span>
        </label>
        <div class="rounded-box border border-base-300 bg-base-200/50 px-4 py-3 text-sm font-medium">
            {{ $module->title }}
        </div>
    </div>

    <div class="form-control flex flex-col gap-2">
        <label class="label p-0" for="course-class-name">
            <span class="label-text font-medium">{{ __('Nome classe') }}</span>
        </label>
        <input id="course-class-name" type="text" class="input input-bordered w-full" name="name" required>
    </div>

    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h4 class="font-semibold">{{ __('Date e orari') }}</h4>
                <p class="text-sm text-base-content/70">{{ __('Una classe può avere uno o più slot.') }}</p>
            </div>
            <button type="button" class="btn btn-outline btn-sm" data-add-course-class-schedule>
                <x-lucide-plus class="h-4 w-4" />
                <span>{{ __('Aggiungi slot') }}</span>
            </button>
        </div>

        <div class="space-y-4" data-course-class-schedules></div>
    </div>

    <div class="grid gap-3 sm:grid-cols-3 hidden" data-course-class-edit-tools>
        <button type="button" class="btn btn-outline justify-between" data-manage-class-users>
            <span>{{ __('Utenti') }}</span>
            <span class="badge badge-primary badge-outline" data-class-detail-users></span>
        </button>
        <button type="button" class="btn btn-outline justify-between" data-manage-class-teachers>
            <span>{{ __('Docenti') }}</span>
            <span class="badge badge-secondary badge-outline" data-class-detail-teachers></span>
        </button>
        <button type="button" class="btn btn-outline justify-between" data-manage-class-tutors>
            <span>{{ __('Tutor') }}</span>
            <span class="badge badge-accent badge-outline" data-class-detail-tutors></span>
        </button>
    </div>

    <p class="text-sm text-error hidden" data-course-class-form-error></p>

    <div class="modal-action mt-0">
        <button type="button" class="btn btn-ghost" data-close-course-class-modal>
            {{ __('Annulla') }}
        </button>
        <button type="submit" class="btn btn-primary" data-loading-text="{{ __('Salvataggio...') }}">
            <span>{{ __('Salva') }}</span>
            <x-lucide-save class="h-4 w-4" />
        </button>
    </div>
</form>
