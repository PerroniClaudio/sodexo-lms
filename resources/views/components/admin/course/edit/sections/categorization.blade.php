@props([
    'course',
    'courseCategories',
    'courseEventTypeLabels',
    'courseTypeLabels',
    'courseValidator',
    'updateUrl',
])

@php
    $selectedCategoryIds = collect(old(
        'category_ids',
        $course->categories->pluck('id')->map(fn ($id) => (string) $id)->all(),
    ))->map(fn ($id) => (string) $id);
    $selectedEventType = old('event_type', $course->event_type);
@endphp

<div class="flex flex-col gap-6">
    <x-admin.course.edit-badge-bar :data="get_defined_vars()" />

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div>
                <h2 class="card-title">{{ __('Categorizzazione') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Seleziona le categorie da associare a questo corso.') }}
                </p>
            </div>

            <form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="form-control w-full">
                        <span class="label">
                            <span class="label-text font-medium">{{ __('Tipologia evento') }}</span>
                        </span>
                        <select name="event_type" class="select select-bordered w-full">
                            <option value="">{{ __('Seleziona tipologia evento') }}</option>
                            @foreach ($courseEventTypeLabels as $eventType => $eventTypeLabel)
                                <option value="{{ $eventType }}" @selected($selectedEventType === $eventType)>{{ $eventTypeLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @error('event_type')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror

                @if ($courseCategories->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
                        {{ __('Non ci sono ancora categorie corso configurate.') }}
                    </div>
                @else
                    <div class="grid grid-cols-1">
                        <label class="form-control w-full">
                            <span class="label">
                                <span class="label-text font-medium">{{ __('Categorie') }}</span>
                            </span>
                            <div class="overflow-x-auto rounded-box border border-base-300 w-full">
                                <table class="table table-zebra">
                                    <thead>
                                        <tr>
                                            <th class="w-16">{{ __('Seleziona') }}</th>
                                            <th>{{ __('Categoria') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($courseCategories as $courseCategory)
                                            <tr>
                                                <td>
                                                    <input
                                                        type="checkbox"
                                                        name="category_ids[]"
                                                        value="{{ $courseCategory->getKey() }}"
                                                        class="checkbox checkbox-primary"
                                                        aria-label="{{ __('Associa categoria :name', ['name' => $courseCategory->name]) }}"
                                                        @checked($selectedCategoryIds->contains((string) $courseCategory->getKey()))
                                                    >
                                                </td>
                                                <td class="font-medium">{{ $courseCategory->name }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </label>
                    </div>
                @endif

                @error('category_ids')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
                @error('category_ids.*')
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
