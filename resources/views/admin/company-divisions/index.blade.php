<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Divisioni aziendali')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.company-divisions.create')"
        />

        <div class="overflow-x-auto rounded-box border border-base-300 bg-base-100">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Partita IVA') }}</th>
                        <th>{{ __('Utenti') }}</th>
                        <th>{{ __('Admin') }}</th>
                        <th>{{ __('Corsi') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($companyDivisions as $division)
                        <tr>
                            <td class="font-medium">{{ $division->name }}</td>
                            <td>{{ $division->vat_number ?? '-' }}</td>
                            <td>{{ $division->users_count }}</td>
                            <td>{{ $division->admins_count }}</td>
                            <td>{{ $division->courses_count }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.company-divisions.edit', $division) }}" class="btn btn-primary btn-sm">
                                    {{ __('Modifica') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-base-content/60">{{ __('Nessuna divisione aziendale disponibile.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $companyDivisions->links() }}
    </div>
</x-layouts.admin>
