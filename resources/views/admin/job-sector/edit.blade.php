<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header
            :title="__('Modifica settore')"
            :description="__('Gestisci i settori.')"
        >
            <x-slot:actions>
                @if($sector->trashed())
                    <form method="POST" action="{{ route('admin.job-sectors.restore', $sector->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-outline">
                            <x-lucide-refresh-cw class="h-4 w-4" />
                            <span>{{ __('Ripristina settore') }}</span>
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.job-sectors.destroy', $sector) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questo settore?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error btn-outline">
                            <x-lucide-trash-2 class="h-4 w-4" />
                            <span>{{ __('Elimina settore') }}</span>
                        </button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <form method="POST" action="{{ route('admin.job-sectors.update', $sector) }}" class="flex flex-col gap-6">
                    @csrf
                    @method('PUT')

                    <div class="grid gap-6 md:grid-cols-1">
                        <div class="form-control flex flex-col gap-2">
                            <label for="name" class="label p-0">
                                <span class="label-text font-medium">{{ __('Nome') }}</span>
                            </label>
                            <input
                                id="name"
                                name="name"
                                type="text"
                                value="{{ old('name', $sector->name) }}"
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
                        >{{ old('description', $sector->description) }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-control flex flex-col gap-2">
                        <label for="manual_risk_level" class="label p-0">
                            <span class="label-text font-medium">{{ __('Rischio manuale di fallback') }}</span>
                        </label>
                        <select
                            id="manual_risk_level"
                            name="manual_risk_level"
                            class="select select-bordered w-full @error('manual_risk_level') select-error @enderror"
                        >
                            <option value="">{{ __('Nessuno') }}</option>
                            @foreach (\App\Enums\RiskLevel::ordered() as $riskLevel)
                                <option value="{{ $riskLevel->value }}" @selected(old('manual_risk_level', $sector->manual_risk_level?->value) === $riskLevel->value)>
                                    {{ $riskLevel->label() }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-sm text-base-content/70">
                            {{ __('Se il settore non ha codici ATECO associati viene usato questo rischio. Se ha ATECO associati viene considerato il rischio più alto tra questo valore e quello calcolato dai codici.') }}
                        </p>
                        @error('manual_risk_level')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.job-sectors.index') }}" class="btn btn-ghost">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva modifiche') }}</span>
                            <x-lucide-check class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Codici ATECO associati --}}
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="card-title">{{ __('Codici ATECO') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Associa codici ATECO per calcolare il rischio del settore') }}
                        </p>
                    </div>
                    <div id="sectorRiskBadge">
                        @php
                            $sectorRisk = $sector->getRiskLevel();
                        @endphp
                        @if($sectorRisk)
                            <span class="badge {{ $sectorRisk->badgeColor() }} badge-lg">
                                {{ __('Rischio') }}: {{ $sectorRisk->label() }}
                            </span>
                        @else
                            <span class="badge badge-ghost badge-lg">
                                {{ __('Rischio') }}: {{ __('N/D') }}
                            </span>
                        @endif
                    </div>
                </div>

                {{-- Form per aggiungere nuovo codice ATECO --}}
                <form method="POST" action="{{ route('admin.job-sectors.ateco.attach', $sector) }}" class="flex flex-col gap-4 rounded-lg border border-base-300 bg-base-50 p-4" id="atecoForm">
                    @csrf
                    <h3 class="font-medium">{{ __('Aggiungi codice ATECO') }}</h3>
                    
                    <div class="alert alert-info">
                        <x-lucide-info class="h-5 w-5" />
                        <span>{{ __('La selezione di un codice include automaticamente tutti i codici sottostanti nella gerarchia (es: selezionare una Sezione include tutte le relative Divisioni, Gruppi, ecc.)') }}</span>
                    </div>
                    
                    <x-searchable-select
                        name="nace_ateco_code"
                        id="nace_ateco_code"
                        :required="true"
                        :selected-value="old('nace_ateco_code')"
                        :options="collect($allAtecoCodes)->flatMap(function ($codes, $hierarchy) {
                            return $codes->map(function ($code) use ($hierarchy) {
                                $hierarchyLabel = \App\Enums\HierarchyLevel::from((int) $hierarchy)->label();

                                return [
                                    'value' => $code->code,
                                    'label' => $code->code.' - '.$code->title_it,
                                    'search' => implode(' ', array_filter([$code->code, $code->title_it, $hierarchyLabel])),
                                    'badge' => $code->code,
                                    'description' => $hierarchyLabel,
                                ];
                            });
                        })->values()->all()"
                        :label="__('Codice ATECO')"
                        :placeholder="__('Cerca o seleziona un codice ATECO...')"
                    />

                    <input type="hidden" id="inclusion_type" name="inclusion_type" value="">

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <x-lucide-plus class="h-4 w-4" />
                            <span>{{ __('Aggiungi codice') }}</span>
                        </button>
                    </div>
                </form>

                @php
                    $naceAtecoCodes = $sector->naceAtecoCodes;
                @endphp

                @if($naceAtecoCodes->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Codice') }}</th>
                                    <th>{{ __('Descrizione') }}</th>
                                    <th>{{ __('Tipo Inclusione') }}</th>
                                    <th>{{ __('Rischio') }}</th>
                                    <th>{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($naceAtecoCodes as $code)
                                    <tr>
                                        <td>
                                            <code class="bg-base-200 px-2 py-1 rounded">{{ $code->code }}</code>
                                        </td>
                                        <td>{{ $code->title_it }}</td>
                                        <td>
                                            @php
                                                $inclusionType = \App\Enums\InclusionType::from($code->pivot->inclusion_type);
                                            @endphp
                                            <span class="badge badge-outline">{{ $inclusionType->label() }}</span>
                                        </td>
                                        <td>
                                            @if($code->risk)
                                                <span class="badge {{ $code->risk->badgeColor() }}">
                                                    {{ $code->risk->label() }}
                                                </span>
                                            @else
                                                <span class="text-base-content/50">{{ __('N/D') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.job-sectors.ateco.detach', [$sector, $code->code]) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="btn btn-error btn-sm"
                                                    onclick="return confirm('{{ __('Sei sicuro di voler rimuovere questo codice?') }}')"
                                                >
                                                    <x-lucide-trash-2 class="h-4 w-4" />
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert">
                        <x-lucide-info class="h-5 w-5" />
                        <span>{{ __('Nessun codice ATECO associato') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // Mappatura tra hierarchy e inclusion_type
        const hierarchyToInclusionType = {
            1: 'section',
            2: 'division',
            3: 'group',
            4: 'class',
            5: 'category',
            6: 'full_code'
        };

        const naceAtecoHierarchyMap = @json(
            collect($allAtecoCodes)->flatMap(function ($codes, $hierarchy) {
                return $codes->mapWithKeys(fn ($code) => [$code->code => (int) $hierarchy]);
            })
        );

        // Imposta automaticamente il tipo di inclusione in base al codice selezionato
        document.getElementById('nace_ateco_code').addEventListener('change', function() {
            const hierarchy = naceAtecoHierarchyMap[this.value];
            const inclusionTypeInput = document.getElementById('inclusion_type');
            
            if (hierarchy && hierarchyToInclusionType[hierarchy]) {
                inclusionTypeInput.value = hierarchyToInclusionType[hierarchy];
            } else {
                inclusionTypeInput.value = '';
            }
        });

        // Funzione per aggiornare il badge del rischio del settore via AJAX
        function updateSectorRiskBadge() {
            const sectorId = {{ $sector->id }};
            const badgeContainer = document.getElementById('sectorRiskBadge');
            const riskUrl = '{{ route('admin.job-sectors.risk', ['job_sector' => $sector->id]) }}';
            
            fetch(riskUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.risk) {
                        badgeContainer.innerHTML = `<span class="badge ${data.badgeColor} badge-lg">{{ __('Rischio') }}: ${data.label}</span>`;
                    } else {
                        badgeContainer.innerHTML = `<span class="badge badge-ghost badge-lg">{{ __('Rischio') }}: {{ __('N/D') }}</span>`;
                    }
                })
                .catch(error => {
                    console.error('Errore nel caricamento del rischio:', error);
                });
        }

        // Intercetta il submit del form e aggiorna il badge dopo il redirect
        document.getElementById('atecoForm').addEventListener('submit', function() {
            // Aspetta un momento per permettere al server di processare
            setTimeout(() => {
                updateSectorRiskBadge();
            }, 1000);
        });

        // Aggiungi listener ai form di eliminazione per aggiornare il badge
        document.querySelectorAll('form[action*="ateco"]').forEach(form => {
            if (form.querySelector('button[type="submit"]')) {
                form.addEventListener('submit', function(e) {
                    // Solo per i form DELETE (rimozione)
                    if (this.querySelector('input[name="_method"][value="DELETE"]')) {
                        // Aspetta un momento per permettere al server di processare
                        setTimeout(() => {
                            updateSectorRiskBadge();
                        }, 1000);
                    }
                });
            }
        });
    </script>
</x-layouts.admin>
