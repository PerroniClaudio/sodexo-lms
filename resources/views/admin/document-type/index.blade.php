<x-layouts.admin>
    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8"
        data-document-types-page
        data-index-url="{{ route('admin.api.document-types.index') }}"
        data-initial-search="{{ $tableSearch }}"
        data-initial-sort="{{ $tableSort }}"
        data-initial-direction="{{ $tableDirection }}"
        data-initial-show-trashed="{{ $showTrashed ? '1' : '0' }}"
    >
        <x-page-header :title="__('Tipologie documento')">
            <x-slot:actions>
                <a href="{{ route('admin.document-types.create') }}" class="btn btn-primary">{{ __('Nuova tipologia') }}</a>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <label class="label cursor-pointer justify-start gap-3 p-0">
                        <input type="checkbox" class="checkbox" data-document-types-show-trashed @checked($showTrashed)>
                        <span class="label-text">{{ __('Mostra eliminati') }}</span>
                    </label>

                    <div class="flex w-full max-w-xl items-center gap-2">
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                class="grow"
                                data-document-types-search
                                value="{{ $tableSearch }}"
                                placeholder="{{ __('Cerca nome o descrizione') }}"
                            >
                        </label>
                        <button type="button" class="btn btn-primary" data-document-types-search-button>
                            {{ __('Cerca') }}
                        </button>
                    </div>
                </div>

                <div class="hidden rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/70" data-document-types-loading>
                    {{ __('Caricamento tipologie documento in corso...') }}
                </div>

                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="id">
                                        <span>{{ __('ID') }}</span>
                                        <span data-sort-icon="id"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="name">
                                        <span>{{ __('Nome') }}</span>
                                        <span data-sort-icon="name"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="description">
                                        <span>{{ __('Descrizione') }}</span>
                                        <span data-sort-icon="description"></span>
                                    </button>
                                </th>
                                <th>
                                    <button type="button" class="inline-flex items-center gap-2" data-sort-key="status">
                                        <span>{{ __('Stato') }}</span>
                                        <span data-sort-icon="status"></span>
                                    </button>
                                </th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody data-document-types-tbody></tbody>
                    </table>
                </div>

                <div class="rounded-box border border-dashed border-base-300 px-4 py-6 text-center text-sm text-base-content/70" data-document-types-empty>
                    {{ __('Nessuna tipologia documento trovata.') }}
                </div>

                <div class="flex flex-col gap-4 border-t border-base-300 pt-4 lg:flex-row lg:items-center lg:justify-between">
                    <p class="text-sm text-base-content/70" data-document-types-summary>0 tipologie documento</p>
                    <div class="join self-start lg:self-auto" data-document-types-pagination></div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
