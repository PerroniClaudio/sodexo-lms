<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Livelli lingua')"
            :action-label="__('Crea nuovo')"
            :action-url="route('admin.language-levels.create')"
        />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.language-levels.default.update') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
                    @csrf
                    <div class="form-control flex flex-col gap-2">
                        <label for="default_language_level_id" class="label p-0">
                            <span class="label-text font-medium">{{ __('Livello di default per nuovi corsi') }}</span>
                        </label>
                        <select id="default_language_level_id" name="default_language_level_id" class="select select-bordered w-full">
                            @foreach ($languageLevels as $languageLevel)
                                <option value="{{ $languageLevel->id }}" @selected($languageLevel->is_default)>
                                    {{ strtoupper($languageLevel->name) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('Salva default') }}</button>
                </form>

                <div class="rounded-box border border-base-300 bg-base-200/40 px-4 py-3 text-sm text-base-content/70">
                    {{ __('Trascina i livelli per cambiare la gerarchia.') }}
                </div>

                <div class="overflow-x-auto rounded-box border border-base-300">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th class="w-16"></th>
                                <th>{{ __('Nome') }}</th>
                                <th>{{ __('Ordine') }}</th>
                                <th>{{ __('Default') }}</th>
                                <th class="text-right">{{ __('Azioni') }}</th>
                            </tr>
                        </thead>
                        <tbody data-language-levels-sortable-list data-reorder-url="{{ route('admin.language-levels.reorder') }}">
                            @foreach ($languageLevels as $languageLevel)
                                <tr
                                    draggable="true"
                                    data-language-level-item
                                    data-language-level-id="{{ $languageLevel->id }}"
                                    class="transition-shadow"
                                >
                                    <td>
                                        <div class="flex h-9 w-9 cursor-move items-center justify-center rounded-full border border-base-300 text-base-content/60">
                                            <x-lucide-move class="h-4 w-4" />
                                        </div>
                                    </td>
                                    <td class="font-medium">{{ strtoupper($languageLevel->name) }}</td>
                                    <td>{{ $languageLevel->sort_order }}</td>
                                    <td>
                                        @if ($languageLevel->is_default)
                                            <span class="badge badge-success badge-outline h-fit">{{ __('Si') }}</span>
                                        @else
                                            <span class="badge badge-ghost badge-outline h-fit">{{ __('No') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('admin.language-levels.edit', $languageLevel) }}" class="btn btn-primary btn-sm">
                                                {{ __('Modifica') }}
                                            </a>
                                            <form method="POST" action="{{ route('admin.language-levels.destroy', $languageLevel) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-error btn-sm" onclick="return confirm('{{ __('Sei sicuro di voler eliminare questo livello?') }}')">
                                                    {{ __('Elimina') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @vite('resources/js/pages/admin-language-level-index.js')
</x-layouts.admin>
