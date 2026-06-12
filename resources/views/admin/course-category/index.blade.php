<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Categorie corsi')">
            <x-slot:actions>
                <a href="{{ route('admin.course-categories.create') }}" class="btn btn-primary">{{ __('Nuova categoria') }}</a>
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="GET" action="{{ route('admin.course-categories.index') }}" class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <label class="label cursor-pointer justify-start gap-3 p-0">
                        <input type="checkbox" name="show_trashed" value="1" class="checkbox" @checked($showTrashed)>
                        <span class="label-text">{{ __('Mostra eliminati') }}</span>
                    </label>

                    <div class="flex w-full max-w-xl items-center gap-2">
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                name="search"
                                class="grow"
                                value="{{ $tableSearch }}"
                                placeholder="{{ __('Cerca nome') }}"
                            >
                        </label>
                        <button type="submit" class="btn btn-primary">{{ __('Cerca') }}</button>
                    </div>
                </form>

                @if ($courseCategories->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 px-4 py-6 text-center text-sm text-base-content/70">
                        {{ __('Nessuna categoria corso trovata.') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Nome') }}</th>
                                    <th>{{ __('Stato') }}</th>
                                    <th class="text-right">{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($courseCategories as $courseCategory)
                                    <tr>
                                        <td>{{ $courseCategory->getKey() }}</td>
                                        <td class="font-medium">{{ $courseCategory->name }}</td>
                                        <td>
                                            @if ($courseCategory->trashed())
                                                <span class="badge badge-outline badge-error">{{ __('Eliminata') }}</span>
                                            @else
                                                <span class="badge badge-outline badge-success">{{ __('Attiva') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="flex justify-end gap-2">
                                                @if ($courseCategory->trashed())
                                                    <form method="POST" action="{{ route('admin.course-categories.restore', $courseCategory->getKey()) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-outline btn-sm">{{ __('Ripristina') }}</button>
                                                    </form>
                                                @else
                                                    <a href="{{ route('admin.course-categories.edit', $courseCategory) }}" class="btn btn-outline btn-primary btn-sm">{{ __('Modifica') }}</a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $courseCategories->links() }}
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
