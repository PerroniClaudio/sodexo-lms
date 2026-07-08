<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuova divisione aziendale')" />

        <x-admin.company-divisions.details :company-division="$companyDivision" />
    </div>
</x-layouts.admin>
