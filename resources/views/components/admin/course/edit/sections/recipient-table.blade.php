@props([
    'items',
    'selectedIds',
    'inputName',
    'title',
    'emptyMessage',
])

<div class="flex flex-col gap-3" data-recipient-table data-page-size="10">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 class="font-semibold">{{ $title }}</h3>
        @if ($items->isNotEmpty())
            <label class="input input-bordered flex w-full items-center gap-2 sm:max-w-xs">
                <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                <input
                    type="search"
                    class="grow"
                    data-recipient-search
                    placeholder="{{ __('Cerca') }}"
                    aria-label="{{ __('Cerca in :title', ['title' => $title]) }}"
                >
            </label>
        @endif
    </div>

    @if ($items->isEmpty())
        <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
            {{ $emptyMessage }}
        </div>
    @else
        <div class="overflow-x-auto rounded-box border border-base-300">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th class="w-16">{{ __('Seleziona') }}</th>
                        <th>{{ __('Nome') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr data-recipient-row data-recipient-name="{{ Str::lower($item->name) }}">
                            <td>
                                <input
                                    type="checkbox"
                                    name="{{ $inputName }}[]"
                                    value="{{ $item->getKey() }}"
                                    class="checkbox checkbox-primary"
                                    data-auto-submit
                                    aria-label="{{ __('Seleziona :name', ['name' => $item->name]) }}"
                                    @checked($selectedIds->contains((string) $item->getKey()))
                                >
                            </td>
                            <td>
                                <span class="font-medium">{{ $item->name }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-3 text-sm text-base-content/70 sm:flex-row sm:items-center sm:justify-between">
            <p data-recipient-summary></p>
            <div class="join">
                <button type="button" class="btn btn-outline btn-sm join-item" data-recipient-prev>{{ __('Prec') }}</button>
                <button type="button" class="btn btn-outline btn-sm join-item" data-recipient-next>{{ __('Succ') }}</button>
            </div>
        </div>
        <div class="hidden rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70" data-recipient-empty>
            {{ __('Nessun risultato.') }}
        </div>
    @endif
</div>
