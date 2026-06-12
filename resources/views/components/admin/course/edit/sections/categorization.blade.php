@props([
    'course',
    'courseCategories',
    'courseTypeLabels',
    'courseValidator',
    'updateUrl',
])

@php
    $selectedCategoryIds = collect(old(
        'category_ids',
        $course->categories->pluck('id')->map(fn ($id) => (string) $id)->all(),
    ))->map(fn ($id) => (string) $id);
@endphp

<div class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

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

                @if ($courseCategories->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-200/40 p-4 text-sm text-base-content/70">
                        {{ __('Non ci sono ancora categorie corso configurate.') }}
                    </div>
                @else
                    <div class="overflow-x-auto rounded-box border border-base-300">
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
