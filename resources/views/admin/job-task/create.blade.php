<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Nuova mansione')" />

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.job-tasks.store') }}" class="flex flex-col gap-6">
                    @csrf

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="form-control flex flex-col gap-2">
                            <label for="name" class="label p-0">
                                <span class="label-text font-medium">{{ __('Nome') }}</span>
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

                        <div class="form-control flex flex-col gap-2">
                            <label for="code" class="label p-0">
                                <span class="label-text font-medium">{{ __('Codice') }}</span>
                            </label>
                            <input
                                id="code"
                                name="code"
                                type="text"
                                value="{{ old('code') }}"
                                class="input input-bordered w-full @error('code') input-error @enderror"
                            >
                            @error('code')
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

                    <div class="divider"></div>

                    <div>
                        <h3 class="text-base font-medium">{{ __('Rischio globale (opzionale)') }}</h3>
                        <p class="text-sm text-base-content/70">
                            {{ __('Questi valori vengono applicati solo ai settori per i quali non esiste un\'associazione diretta mansione-settore.') }}
                        </p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="form-control flex flex-col gap-2">
                            <label for="global_risk_level" class="label p-0">
                                <span class="label-text font-medium">{{ __('Livello di rischio globale') }}</span>
                            </label>
                            <select
                                id="global_risk_level"
                                name="global_risk_level"
                                class="select select-bordered w-full @error('global_risk_level') select-error @enderror"
                            >
                                <option value="">{{ __('Nessuno') }}</option>
                                <option value="low" @selected(old('global_risk_level') === 'low')>{{ __('Basso') }}</option>
                                <option value="medium" @selected(old('global_risk_level') === 'medium')>{{ __('Medio') }}</option>
                                <option value="high" @selected(old('global_risk_level') === 'high')>{{ __('Alto') }}</option>
                            </select>
                            @error('global_risk_level')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-control flex flex-col gap-2">
                            <label class="label cursor-pointer justify-start gap-3">
                                <input
                                    type="checkbox"
                                    name="global_sector_risk_override"
                                    value="1"
                                    class="checkbox checkbox-primary"
                                    @checked(old('global_sector_risk_override'))
                                >
                                <span class="label-text">{{ __('Consenti override del rischio settore') }}</span>
                            </label>
                            <p class="text-xs text-base-content/60">
                                {{ __('Se attivo, il rischio globale può sovrascrivere il rischio del settore (necessario DVR).') }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.job-tasks.index') }}" class="btn btn-ghost">
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
</x-layouts.admin>
