@props(['companyDivision', 'users'])

@php
    $selectedUserIds = collect(old('user_ids', $companyDivision->users()->pluck('users.id')->all()))->map(fn ($id) => (int) $id);
@endphp

<form method="POST" action="{{ route('admin.company-divisions.update', $companyDivision) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="name" value="{{ $companyDivision->name }}">
    <input type="hidden" name="vat_number" value="{{ $companyDivision->vat_number }}">
    <input type="hidden" name="sync_users" value="1">

    <section class="card border border-base-300 bg-base-100 shadow-sm" data-association-card="users">
        <div class="card-body gap-4">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="card-title">{{ __('Utenti associati') }}</h2>
                    <p class="text-sm text-base-content/70">{{ __('User appartenenti alla divisione.') }}</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="company_division_users_modal.showModal()">
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
                        @foreach ($users as $user)
                            <tr data-selected-row="user-{{ $user->getKey() }}" @class(['hidden' => ! $selectedUserIds->contains((int) $user->getKey())])>
                                <td>{{ trim($user->surname.' '.$user->name) }}</td>
                                <td>{{ $user->email }}</td>
                            </tr>
                        @endforeach
                        <tr data-empty-row="users" @class(['hidden' => $selectedUserIds->isNotEmpty()])>
                            <td colspan="2" class="text-center text-base-content/60">{{ __('Nessun utente associato.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    <dialog id="company_division_users_modal" class="modal">
        <div class="modal-box max-w-5xl">
            <div class="flex items-center justify-between gap-4">
                <h3 class="text-lg font-semibold">{{ __('Aggiungi utenti') }}</h3>
                <button type="button" class="btn btn-ghost btn-sm btn-circle" aria-label="{{ __('Chiudi') }}" onclick="company_division_users_modal.close()">
                    <x-lucide-x class="h-4 w-4" />
                </button>
            </div>
            <label class="input input-bordered mt-4 flex items-center gap-2">
                <x-lucide-search class="h-4 w-4 text-base-content/60" />
                <input type="search" class="grow" placeholder="{{ __('Cerca utenti') }}" data-association-search="users">
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
                        @foreach ($users as $user)
                            <tr data-searchable-row="users" data-search-text="{{ \Illuminate\Support\Str::lower(trim($user->surname.' '.$user->name).' '.$user->email) }}">
                                <td>
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->getKey() }}" class="checkbox checkbox-primary" @checked($selectedUserIds->contains((int) $user->getKey()))>
                                </td>
                                <td>{{ trim($user->surname.' '.$user->name) }}</td>
                                <td>{{ $user->email }}</td>
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
