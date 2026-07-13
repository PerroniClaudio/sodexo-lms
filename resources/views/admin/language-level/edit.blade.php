<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica livello lingua')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.language-levels.update', $languageLevel) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')
                    <x-admin.language-level.form-fields :data="array_merge(get_defined_vars(), ['languageLevel' => $languageLevel])" />

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.language-levels.index') }}" class="btn btn-ghost">{{ __('Annulla') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('Salva') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
