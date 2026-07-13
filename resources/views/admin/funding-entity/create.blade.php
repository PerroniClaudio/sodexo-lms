<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuovo ente finanziatore')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.funding-entities.store') }}" class="flex flex-col gap-6">
                    @csrf

                    <x-admin.funding-entity.form-fields :data="get_defined_vars()" />

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.funding-entities.index') }}" class="btn btn-ghost">{{ __('Annulla') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Salva e continua') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
