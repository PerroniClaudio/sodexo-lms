@props(['data' => []])

@php
    extract($data);
@endphp

<div>
    <div class="rounded-box border border-base-300 bg-base-200/40 p-4 text-sm text-base-content/80">
        {{ __('Questo modulo usa il questionario globale di gradimento configurato dai superadmin. Le risposte sono anonime e associate solo al corso.') }}

        @role('superadmin')
            <div class="mt-3">
                <a href="{{ route('admin.satisfaction-survey.edit') }}" class="btn btn-sm btn-primary">
                    {{ __('Configura questionario globale') }}
                </a>
            </div>
        @endrole
    </div>
</div>
<x-admin.module.validity-badge :data="get_defined_vars()" />
<x-admin.module.readonly-title :data="get_defined_vars()" />
<x-admin.module.description :data="get_defined_vars()" />
<x-admin.module.status :data="get_defined_vars()" />
