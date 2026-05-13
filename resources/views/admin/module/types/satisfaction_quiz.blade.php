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
@include('admin.module.partials.module-validity-badge')
@include('admin.module.partials.readonly-title')
@include('admin.module.partials.description')
@include('admin.module.partials.status')
