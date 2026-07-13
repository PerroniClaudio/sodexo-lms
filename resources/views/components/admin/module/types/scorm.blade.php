@props(['data' => []])

@php
    extract($data);
@endphp

<x-admin.module.validity-badge :data="get_defined_vars()" />
<x-admin.module.editable-title :data="get_defined_vars()" />
<x-admin.module.description :data="get_defined_vars()" />
<x-admin.module.status :data="get_defined_vars()" />

<div class="rounded-box border border-base-300 bg-base-200/40 p-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold">{{ __('Pacchetto SCORM') }}</p>
            <p class="text-sm text-base-content/70">
                {{ __('Carica, aggiorna e lancia il pacchetto SCORM associato a questo modulo.') }}
            </p>
            <p class="mt-2 text-sm text-base-content/70">
                {{ __('Limiti attivi: un solo modulo SCORM per corso e un solo pacchetto SCORM per modulo.') }}
            </p>
        </div>

        <a href="{{ route('admin.courses.modules.scorm.index', [$course, $module]) }}" class="btn btn-secondary">
            <x-lucide-package class="h-4 w-4" />
            <span>{{ __('Gestisci pacchetti') }}</span>
        </a>
    </div>
</div>
