<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica divisione aziendale')">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.company-divisions.destroy', $companyDivision) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questa divisione?') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-error btn-outline">
                        <x-lucide-trash-2 class="h-4 w-4" />
                        <span>{{ __('Elimina') }}</span>
                    </button>
                </form>
            </x-slot:actions>
        </x-page-header>

        <x-admin.company-divisions.details :company-division="$companyDivision" />

        <div class="flex flex-col gap-4" data-company-division-associations>
            <x-admin.company-divisions.users-card :company-division="$companyDivision" :users="$users" />
            <x-admin.company-divisions.admins-card :company-division="$companyDivision" :admins="$admins" />
            <x-admin.company-divisions.courses-card :company-division="$companyDivision" :courses="$courses" />
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-association-search]').forEach((input) => {
                input.addEventListener('input', () => {
                    const needle = input.value.trim().toLowerCase();
                    document.querySelectorAll(`[data-searchable-row="${input.dataset.associationSearch}"]`).forEach((row) => {
                        row.classList.toggle('hidden', needle !== '' && ! row.dataset.searchText.includes(needle));
                    });
                });
            });
        });
    </script>
</x-layouts.admin>
