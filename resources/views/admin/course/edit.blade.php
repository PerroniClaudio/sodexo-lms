<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica corso')" />

        <div class="flex flex-col gap-6">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="card-title">{{ __('Dati anagrafici') }}</h2>
                            <p class="text-sm text-base-content/70">
                                {{ __('Gestisci le informazioni principali del corso.') }}
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.courses.update', $course) }}" class="flex flex-col gap-6">
                        @csrf
                        @method('PUT')

                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label for="title" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Titolo del corso') }}</span>
                                </label>
                                <input
                                    id="title"
                                    name="title"
                                    type="text"
                                    value="{{ old('title', $course->title) }}"
                                    class="input input-bordered w-full @error('title') input-error @enderror"
                                    required
                                >
                                @error('title')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label for="description" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                                </label>
                                <textarea
                                    id="description"
                                    name="description"
                                    class="textarea textarea-bordered min-h-32 w-full @error('description') textarea-error @enderror"
                                    required
                                >{{ old('description', $course->description) }}</textarea>
                                @error('description')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label for="year" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Anno del corso') }}</span>
                                </label>
                                <input
                                    id="year"
                                    name="year"
                                    type="number"
                                    value="{{ old('year', $course->year) }}"
                                    class="input input-bordered w-full @error('year') input-error @enderror"
                                    min="1900"
                                    max="2100"
                                    required
                                >
                                @error('year')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-control flex flex-col gap-2">
                                <label for="expiry_date" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Data scadenza') }}</span>
                                </label>
                                <input
                                    id="expiry_date"
                                    name="expiry_date"
                                    type="date"
                                    value="{{ old('expiry_date', $course->expiry_date?->format('Y-m-d')) }}"
                                    class="input input-bordered w-full @error('expiry_date') input-error @enderror"
                                    required
                                >
                                @error('expiry_date')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-control flex flex-col gap-2 md:col-span-2">
                                <label for="status" class="label p-0">
                                    <span class="label-text font-medium">{{ __('Stato') }}</span>
                                </label>
                                <select
                                    id="status"
                                    name="status"
                                    class="select select-bordered w-full @error('status') select-error @enderror"
                                    required
                                >
                                    @foreach ($courseStatusLabels as $courseStatus => $courseStatusLabel)
                                        <option value="{{ $courseStatus }}" @selected(old('status', $course->status) === $courseStatus)>
                                            {{ $courseStatusLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="text-sm text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary">
                                <span>{{ __('Salva dati') }}</span>
                                <x-lucide-save class="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <div>
                        <h2 class="card-title">{{ __('Moduli') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('La gestione dei moduli verrà sviluppata in seguito.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
