<x-layouts.admin>
    @php
        $columns = [
            ['key' => 'surname', 'label' => __('Cognome'), 'sortable' => true],
            ['key' => 'name', 'label' => __('Nome'), 'sortable' => true],
            ['key' => 'email', 'label' => __('Email'), 'sortable' => true],
            ['key' => 'fiscal_code', 'label' => __('CF'), 'sortable' => true],
            ['key' => 'account_type', 'label' => __('Tipo di account'), 'sortable' => true],
            // ['key' => 'role', 'label' => __('Ruolo'), 'sortable' => true],
            ['key' => 'status', 'label' => __('Stato'), 'sortable' => true],
            ['key' => 'actions', 'label' => __('Azioni'), 'sortable' => false],
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Utenti')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.users.create')"
        />

        <x-data-table
            :columns="$columns"
            :rows="$users"
            :sort="$sort"
            :direction="$direction"
            :search="$search"
            :search-placeholder="__('Cerca nome, cognome, CF, email')"
            :empty-message="__('Nessun utente trovato.')"
            :show-search="false"
        >
            <x-slot:filters>
                <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input
                                type="checkbox"
                                name="show_trashed"
                                value="1"
                                class="checkbox"
                                @checked(request('show_trashed'))
                                onchange="this.form.submit()"
                            >
                            <span class="label-text">{{ __('Mostra eliminati') }}</span>
                        </label>
                    </div>

                    <div class="flex w-full max-w-md items-center gap-2">
                        @foreach (request()->query() as $key => $value)
                            @continue(in_array($key, ['search', 'page', 'show_trashed'], true))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                class="grow"
                                placeholder="{{ __('Cerca nome, cognome, CF, email') }}"
                            >
                        </label>

                        <button type="submit" class="btn btn-primary">
                            {{ __('Cerca') }}
                        </button>
                    </div>
                </form>
            </x-slot:filters>
            @foreach ($users as $user)
                <tr class="hover:bg-base-200">
                    <td>{{ $user->surname }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->fiscal_code }}</td>
                    <td>
                        @php $role = $user->getRoleNames()->first(); @endphp
                        {{ $role ? __(ucfirst($role)) : '-' }}
                    </td>
                    {{-- <td>{{ $user->jobRole?->name ?? '-' }}</td> --}}
                    <td>
                        @if($user->trashed())
                            <span class="badge badge-outline badge-error">{{ __('Eliminato') }}</span>
                        @else
                            <span class="badge badge-outline badge-success">{{ __('Attivo') }}</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-2">
                            @if(!$user->trashed())
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary btn-sm">
                                    {{ __('Modifica') }}
                                </a>
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-error btn-sm" onclick="return confirm('Sei sicuro?')">
                                        {{ __('Elimina') }}
                                    </button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.users.restore', $user->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm">
                                        {{ __('Ripristina') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        <div class="mt-4">
            {{ $users->links() }}
        </div>
        </x-data-table>
    </div>
</x-layouts.admin>
