@props(['companyDivision', 'admins'])

@php
    $selectedAdminIds = collect(old('admin_ids', $companyDivision->admins()->pluck('users.id')->all()))->map(fn ($id) => (int) $id);
@endphp

<form method="POST" action="{{ route('admin.company-divisions.update', $companyDivision) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="name" value="{{ $companyDivision->name }}">
    <input type="hidden" name="vat_number" value="{{ $companyDivision->vat_number }}">
    <input type="hidden" name="sync_admins" value="1">

    <section class="card border border-base-300 bg-base-100 shadow-sm" data-association-card="admins">
        <div class="card-body gap-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="card-title">{{ __('Admin associati') }}</h2>
                    <p class="text-sm text-base-content/70">{{ __('Admin che possono lavorare su questa divisione.') }}</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="company_division_admins_modal.showModal()">
                    <x-lucide-plus class="h-4 w-4" />
                    <span>{{ __('Aggiungi') }}</span>
                </button>
            </div>

            <div class="overflow-x-auto rounded-box border border-base-300">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>{{ __('Utente') }}</th>
                            <th>{{ __('Email') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admins as $admin)
                            <tr data-selected-row="admin-{{ $admin->getKey() }}" @class(['hidden' => ! $selectedAdminIds->contains((int) $admin->getKey())])>
                                <td>{{ trim($admin->surname.' '.$admin->name) }}</td>
                                <td>{{ $admin->email }}</td>
                            </tr>
                        @endforeach
                        <tr data-empty-row="admins" @class(['hidden' => $selectedAdminIds->isNotEmpty()])>
                            <td colspan="2" class="text-center text-base-content/60">{{ __('Nessun admin associato.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    <dialog id="company_division_admins_modal" class="modal">
        <div class="modal-box max-w-5xl">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-lg font-semibold">{{ __('Aggiungi admin') }}</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" aria-label="{{ __('Chiudi') }}" onclick="company_division_admins_modal.close()">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>
            <label class="input input-bordered mt-4 flex items-center gap-2">
                <x-lucide-search class="h-4 w-4 text-base-content/60" />
                <input type="search" class="grow" placeholder="{{ __('Cerca admin') }}" data-association-search="admins">
            </label>
            <div class="mt-4 max-h-[28rem] overflow-auto rounded-box border border-base-300">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th></th>
                            <th>{{ __('Utente') }}</th>
                            <th>{{ __('Email') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($admins as $admin)
                            <tr data-searchable-row="admins" data-search-text="{{ \Illuminate\Support\Str::lower(trim($admin->surname.' '.$admin->name).' '.$admin->email) }}">
                                <td>
                                    <input type="checkbox" name="admin_ids[]" value="{{ $admin->getKey() }}" class="checkbox checkbox-primary" @checked($selectedAdminIds->contains((int) $admin->getKey()))>
                                </td>
                                <td>{{ trim($admin->surname.' '.$admin->name) }}</td>
                                <td>{{ $admin->email }}</td>
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
