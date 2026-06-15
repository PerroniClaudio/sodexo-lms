@props([
    'course',
    'partners',
    'updateUrl',
])

@php
    $selectedPartnerIds = collect(old(
        'partner_ids',
        $course->partners->pluck('id')->map(fn ($id) => (string) $id)->all(),
    ))->map(fn ($id) => (string) $id);
@endphp

<div class="flex flex-col gap-6">
    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div>
                <h2 class="card-title">{{ __('Partner') }}</h2>
                <p class="text-sm text-base-content/70">{{ __('Associa partner a questo corso.') }}</p>
            </div>

            <form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6" data-partners-form>
                @csrf
                @method('PUT')

                @if ($partners->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
                        {{ __('Non ci sono ancora partner configurati.') }}
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4">
                        <label class="input input-bordered flex w-full items-center gap-2">
                            <x-lucide-search class="h-4 w-4 shrink-0 text-base-content/60" />
                            <input
                                type="search"
                                class="grow"
                                placeholder="{{ __('Cerca partner') }}"
                                data-partners-search
                            >
                        </label>

                        <label class="form-control w-full">
                            <span class="label">
                                <span class="label-text font-medium">{{ __('Partner') }}</span>
                            </span>
                            <div class="overflow-x-auto rounded-box border border-base-300 w-full">
                                <table class="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th class="w-16">{{ __('Seleziona') }}</th>
                                            <th>{{ __('Ragione sociale') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($partners as $partner)
                                            <tr data-partner-row data-partner-name="{{ \Illuminate\Support\Str::lower($partner->ragione_sociale) }}">
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        name="partner_ids[]"
                                                        value="{{ $partner->getKey() }}"
                                                        class="checkbox checkbox-primary"
                                                        aria-label="{{ __('Associa partner :name', ['name' => $partner->ragione_sociale]) }}"
                                                        @checked($selectedPartnerIds->contains((string) $partner->getKey()))
                                                    >
                                                </td>
                                                <td class="font-medium">{{ $partner->ragione_sociale }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </label>
                    </div>
                @endif

                @error('partner_ids')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
                @error('partner_ids.*')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <span>{{ __('Salva dati') }}</span>
                        <x-lucide-save class="h-4 w-4" />
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-partners-form]');

        if (! form) {
            return;
        }

        const searchInput = form.querySelector('[data-partners-search]');
        const rows = form.querySelectorAll('[data-partner-row]');

        if (! searchInput || rows.length === 0) {
            return;
        }

        searchInput.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();

            rows.forEach(function (row) {
                const name = row.dataset.partnerName ?? '';
                row.classList.toggle('hidden', term !== '' && ! name.includes(term));
            });
        });
    });
</script>
