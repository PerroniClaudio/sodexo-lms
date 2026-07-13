<x-layouts.admin>
    <x-page-header :title="__('Audit amministrativo')" />

    <div class="space-y-6">
        <form method="GET" action="{{ route('admin.audit-events.index') }}" class="grid gap-4 rounded-box border border-base-300 bg-base-100 p-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="form-control"><span class="label-text">{{ __('Dal') }}</span><input class="input input-bordered" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
            <label class="form-control"><span class="label-text">{{ __('Al') }}</span><input class="input input-bordered" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            <label class="form-control"><span class="label-text">{{ __('Attore') }}</span><select class="select select-bordered" name="actor_user_id"><option value="">{{ __('Tutti') }}</option>@foreach($actors as $actor)<option value="{{ $actor->id }}" @selected(($filters['actor_user_id'] ?? null) == $actor->id)>{{ $actor->name }} {{ $actor->surname }}</option>@endforeach</select></label>
            <label class="form-control"><span class="label-text">{{ __('Divisione') }}</span><select class="select select-bordered" name="company_division_id"><option value="">{{ __('Tutte') }}</option>@foreach($companyDivisions as $division)<option value="{{ $division->id }}" @selected(($filters['company_division_id'] ?? null) == $division->id)>{{ $division->name }}</option>@endforeach</select></label>
            <label class="form-control"><span class="label-text">{{ __('Azione') }}</span><select class="select select-bordered" name="action"><option value="">{{ __('Tutte') }}</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>@endforeach</select></label>
            <label class="form-control"><span class="label-text">{{ __('Oggetto') }}</span><select class="select select-bordered" name="subject_type"><option value="">{{ __('Tutti') }}</option>@foreach($subjectTypes as $subjectType)<option value="{{ $subjectType }}" @selected(($filters['subject_type'] ?? null) === $subjectType)>{{ $subjectType }}</option>@endforeach</select></label>
            <label class="form-control"><span class="label-text">{{ __('ID oggetto') }}</span><input class="input input-bordered" type="number" name="subject_id" value="{{ $filters['subject_id'] ?? '' }}"></label>
            <div class="flex items-end gap-2"><button class="btn btn-primary" type="submit">{{ __('Filtra') }}</button><a class="btn btn-ghost" href="{{ route('admin.audit-events.index') }}">{{ __('Reimposta') }}</a></div>
        </form>

        <form method="POST" action="{{ route('admin.audit-events.exports.store') }}" class="flex flex-wrap items-center justify-between gap-3 rounded-box border border-base-300 bg-base-100 p-4">
            @csrf
            @foreach($filters as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
            <p class="text-sm text-base-content/70">{{ __('L’export include i filtri correnti e viene generato nel bucket privato.') }}</p>
            <button class="btn btn-outline" type="submit">{{ __('Esporta CSV') }}</button>
        </form>

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="table table-zebra"><thead><tr><th>{{ __('Quando') }}</th><th>{{ __('Attore') }}</th><th>{{ __('Azione') }}</th><th>{{ __('Oggetto') }}</th><th>{{ __('Divisione') }}</th></tr></thead><tbody>
                @forelse($auditEvents as $event)<tr><td>{{ $event->occurred_at?->format('d/m/Y H:i') }}</td><td>{{ $event->actor_label ?? __('Sistema') }}</td><td><span class="badge badge-outline">{{ $event->action }}</span></td><td>{{ $event->subject_type }} @if($event->subject_id)#{{ $event->subject_id }}@endif — {{ $event->subject_label }}</td><td>{{ $event->companyDivision?->name ?? '—' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-base-content/60">{{ __('Nessun evento trovato.') }}</td></tr>@endforelse
            </tbody></table>
        </div>
        {{ $auditEvents->links() }}
    </div>
</x-layouts.admin>
