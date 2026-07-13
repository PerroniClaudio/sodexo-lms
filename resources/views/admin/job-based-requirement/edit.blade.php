<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica requisito ruolo/mansione')">
            <x-slot:actions>
                <a href="{{ route('admin.job-based-requirements.index') }}" class="btn btn-ghost">
                    <x-lucide-arrow-left class="h-4 w-4" />
                    <span>{{ __('Torna alla lista') }}</span>
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.job-based-requirements.update', $jobBasedRequirement) }}" class="flex flex-col gap-6" data-job-based-requirement-form>
                    @csrf
                    @method('PUT')

                    <x-admin.job-based-requirement.form :data="get_defined_vars()" />

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.job-based-requirements.index') }}" class="btn btn-ghost">
                            {{ __('Annulla') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva modifiche') }}</span>
                            <x-lucide-save class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
