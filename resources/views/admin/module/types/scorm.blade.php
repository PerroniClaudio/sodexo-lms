@include('admin.module.partials.editable-title')
@include('admin.module.partials.description')
@include('admin.module.partials.status')

<div class="rounded-box border border-base-300 bg-base-200/40 p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold">{{ __('Pacchetti SCORM') }}</p>
            <p class="text-sm text-base-content/70">
                {{ __('Carica, aggiorna e lancia i pacchetti SCORM associati a questo modulo.') }}
            </p>
        </div>

        <a href="{{ route('admin.courses.modules.scorm.index', [$course, $module]) }}" class="btn btn-secondary">
            <x-lucide-package class="h-4 w-4" />
            <span>{{ __('Gestisci pacchetti') }}</span>
        </a>
    </div>
</div>
