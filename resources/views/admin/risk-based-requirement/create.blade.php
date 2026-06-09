<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuovo requisito')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.risk-based-requirements.store') }}" class="flex flex-col gap-6">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-1">
                        <div class="form-control flex flex-col gap-2">
                            <label for="name" class="label p-0">
                                <span class="label-text font-medium">{{ __('Nome') }} <span class="text-error">*</span></span>
                            </label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name') }}"
                                class="input input-bordered w-full @error('name') input-error @enderror"
                                required
                            >
                            @error('name')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label for="description" class="label p-0">
                            <span class="label-text font-medium">{{ __('Descrizione') }}</span>
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            class="textarea textarea-bordered w-full @error('description') textarea-error @enderror"
                        >{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label class="label p-0">
                            <span class="label-text font-medium">{{ __('Livelli di rischio') }} <span class="text-error">*</span></span>
                        </label>
                        <div class="flex flex-col gap-2">
                            @foreach($riskLevels as $level)
                                <label class="label cursor-pointer justify-start gap-3">
                                    <input
                                        type="checkbox"
                                        name="risk_levels[]"
                                        value="{{ $level->value }}"
                                        class="checkbox"
                                        @checked(in_array($level->value, old('risk_levels', [])))
                                    >
                                    <span class="badge {{ $level->badgeColor() }}">{{ $level->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('risk_levels')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input
                                type="checkbox"
                                name="is_limited_validity"
                                id="is_limited_validity"
                                value="1"
                                class="checkbox"
                                @checked(old('is_limited_validity', false))
                                onchange="toggleValidityFields()"
                            >
                            <span class="label-text font-medium">{{ __('Validità limitata') }}</span>
                        </label>
                        @error('is_limited_validity')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="validity-fields" class="grid gap-4 md:grid-cols-2" style="display: {{ old('is_limited_validity') ? 'grid' : 'none' }}">
                        <div class="form-control flex flex-col gap-2">
                            <label for="validity_years" class="label p-0">
                                <span class="label-text font-medium">{{ __('Anni') }}</span>
                            </label>
                            <input
                                id="validity_years"
                                name="validity_years"
                                type="number"
                                min="0"
                                value="{{ old('validity_years', 0) }}"
                                class="input input-bordered w-full @error('validity_years') input-error @enderror"
                            >
                            @error('validity_years')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-control flex flex-col gap-2">
                            <label for="validity_months_part" class="label p-0">
                                <span class="label-text font-medium">{{ __('Mesi') }}</span>
                            </label>
                            <input
                                id="validity_months_part"
                                name="validity_months_part"
                                type="number"
                                min="0"
                                max="11"
                                value="{{ old('validity_months_part', 0) }}"
                                class="input input-bordered w-full @error('validity_months_part') input-error @enderror"
                            >
                            @error('validity_months_part')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @error('validity_months')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror

                    <div class="form-control flex flex-col gap-2">
                        <label for="reset_formation_years" class="label p-0">
                            <span class="label-text font-medium">{{ __('Tempo reset formazione (anni)') }}</span>
                        </label>
                        <input
                            id="reset_formation_years"
                            name="reset_formation_years"
                            type="number"
                            min="1"
                            value="{{ old('reset_formation_years') }}"
                            class="input input-bordered w-full @error('reset_formation_years') input-error @enderror"
                        >
                        <p class="text-sm text-base-content/70">
                            {{ __('Tempo dall\'ultimo certificato oltre il quale si deve ripetere questa formazione da capo e non basta l\'aggiornamento.') }}
                        </p>
                        @error('reset_formation_years')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control flex flex-col gap-3">
                        <div>
                            <label class="label p-0">
                                <span class="label-text font-medium">{{ __('Famiglia formativa') }} <span class="text-error">*</span></span>
                            </label>
                            <p class="text-xs text-base-content/70">
                                {{ __('Dato necessario per logiche interne alla formazione specifica dei lavoratori.') }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-3">
                            <label for="training_family_general" class="label cursor-pointer justify-start gap-3 ">
                                <input
                                    id="training_family_general"
                                    name="training_family"
                                    type="radio"
                                    value="general"
                                    class="radio"
                                    @checked(old('training_family', 'general') === 'general')
                                >
                                <span class="label-text">{{ __('Formazione generale') }}</span>
                            </label>
                            <label for="training_family_specific" class="label cursor-pointer justify-start gap-3 ">
                                <input
                                    id="training_family_specific"
                                    name="training_family"
                                    type="radio"
                                    value="specific"
                                    class="radio"
                                    @checked(old('training_family') === 'specific')
                                >
                                <span class="label-text">{{ __('Formazione specifica') }}</span>
                            </label>
                        </div>
                        @error('training_family')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.risk-based-requirements.index') }}" class="btn btn-ghost">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva e continua') }}</span>
                            <x-lucide-arrow-right class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function toggleValidityFields() {
            const checkbox = document.getElementById('is_limited_validity');
            const fields = document.getElementById('validity-fields');
            fields.style.display = checkbox.checked ? 'grid' : 'none';
        }
    </script>
    @endpush
</x-layouts.admin>
