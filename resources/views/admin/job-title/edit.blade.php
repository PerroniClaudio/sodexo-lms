<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica mansione')">
            <x-slot:actions>
                @if($title->trashed())
                    <form method="POST" action="{{ route('admin.job-titles.restore', $title->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-outline">
                            <x-lucide-refresh-cw class="h-4 w-4" />
                            <span>{{ __('Ripristina mansione') }}</span>
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.job-titles.destroy', $title) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questa mansione?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error btn-outline">
                            <x-lucide-trash-2 class="h-4 w-4" />
                            <span>{{ __('Elimina mansione') }}</span>
                        </button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="card-title">{{ __('Dati anagrafici') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Gestisci le mansioni.') }}
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.job-titles.update', $title) }}" class="flex flex-col gap-6">
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
                                value="{{ old('name', $title->name) }}"
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
                        >{{ old('description', $title->description) }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary">
                            {{ __('Salva dati') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Associazioni Settore-Rischio --}}
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="card-title">{{ __('Associazioni Settore-Rischio') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Specifica il livello di rischio di questa mansione per settore specifico (se i rischi differiscono)') }}
                        </p>
                    </div>
                </div>

                {{-- Form per aggiungere nuova associazione --}}
                <form method="POST" action="{{ route('admin.job-titles.sectors.attach', $title) }}" class="flex flex-col gap-4 rounded-lg border border-base-300 bg-base-50 p-4">
                    @csrf
                    <h3 class="font-medium">{{ __('Aggiungi nuova associazione') }}</h3>
                    
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="form-control flex flex-col gap-2">
                            <label for="job_sector_id" class="label p-0">
                                <span class="label-text font-medium">{{ __('Settore') }}</span>
                            </label>
                            <select
                                id="job_sector_id"
                                name="job_sector_id"
                                class="select select-bordered w-full @error('job_sector_id') select-error @enderror"
                                required
                            >
                                <option value="">{{ __('Seleziona un settore') }}</option>
                                @foreach($allSectors as $sector)
                                    <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                                @endforeach
                            </select>
                            @error('job_sector_id')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-control flex flex-col gap-2">
                            <label for="title_risk_level" class="label p-0">
                                <span class="label-text font-medium">{{ __('Livello di Rischio') }}</span>
                            </label>
                            <select
                                id="title_risk_level"
                                name="title_risk_level"
                                class="select select-bordered w-full @error('title_risk_level') select-error @enderror"
                                required
                            >
                                <option value="">{{ __('Seleziona il rischio') }}</option>
                                <option value="low">{{ __('Basso') }}</option>
                                <option value="medium">{{ __('Medio') }}</option>
                                <option value="high">{{ __('Alto') }}</option>
                            </select>
                            @error('title_risk_level')
                                <p class="text-sm text-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <x-lucide-plus class="h-4 w-4" />
                            <span>{{ __('Aggiungi associazione') }}</span>
                        </button>
                    </div>
                </form>

                {{-- Lista associazioni esistenti --}}
                @if($title->jobSectors->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Settore') }}</th>
                                    <th>{{ __('Rischio Settore') }}</th>
                                    <th>{{ __('Rischio Mansione') }}</th>
                                    <th>{{ __('Rischio Effettivo') }}</th>
                                    <th>{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($title->jobSectors as $sector)
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $sector->name }}</div>
                                            <div class="text-xs text-base-content/60">
                                                {{ $sector->description }}
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $sectorRisk = $sector->getRiskLevel();
                                            @endphp
                                            <span class="badge {{ $sectorRisk->badgeColor() }}">
                                                {{ $sectorRisk->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $titleRisk = \App\Enums\RiskLevel::from($sector->pivot->title_risk_level);
                                            @endphp
                                            <span class="badge {{ $titleRisk->badgeColor() }}">
                                                {{ $titleRisk->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $effectiveRisk = $sector->getEffectiveWorkerRisk($title->id);
                                            @endphp
                                            <span class="badge {{ $effectiveRisk->badgeColor() }}">
                                                {{ $effectiveRisk->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-primary btn-sm"
                                                    onclick="openEditRiskModal({{ $sector->id }}, '{{ $sector->name }}', '{{ $titleRisk->value }}')"
                                                >
                                                    <x-lucide-edit class="h-4 w-4" />
                                                </button>
                                                <form method="POST" action="{{ route('admin.job-titles.sectors.detach', [$title, $sector]) }}" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="btn btn-error btn-sm"
                                                        onclick="return confirm('{{ __('Sei sicuro di voler rimuovere questa associazione?') }}')"
                                                    >
                                                        <x-lucide-trash-2 class="h-4 w-4" />
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert">
                        <x-lucide-info class="h-5 w-5" />
                        <span>{{ __('Nessuna associazione settore-rischio configurata') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal per modifica rischio --}}
    <dialog id="editRiskModal" class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ __('Modifica Rischio Mansione') }}</h3>
            <p class="py-4 text-sm text-base-content/70">
                {{ __('Modifica il livello di rischio per') }} <span id="modalSectorName" class="font-medium"></span>
            </p>
            
            <form id="editRiskForm" method="POST" action="">
                @csrf
                @method('PUT')
                
                <div class="form-control flex flex-col gap-2">
                    <label for="modal_title_risk_level" class="label p-0">
                        <span class="label-text font-medium">{{ __('Livello di Rischio') }}</span>
                    </label>
                    <select
                        id="modal_title_risk_level"
                        name="title_risk_level"
                        class="select select-bordered w-full"
                        required
                    >
                        <option value="low">{{ __('Basso') }}</option>
                        <option value="medium">{{ __('Medio') }}</option>
                        <option value="high">{{ __('Alto') }}</option>
                    </select>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn" onclick="document.getElementById('editRiskModal').close()">
                        {{ __('Annulla') }}
                    </button>
                    <button type="submit" class="btn btn-primary">
                        {{ __('Salva') }}
                    </button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>{{ __('Chiudi') }}</button>
        </form>
    </dialog>

    <script>
        function openEditRiskModal(sectorId, sectorName, currentRisk) {
            const modal = document.getElementById('editRiskModal');
            const form = document.getElementById('editRiskForm');
            const sectorNameSpan = document.getElementById('modalSectorName');
            const riskSelect = document.getElementById('modal_title_risk_level');
            
            // Imposta il nome del settore
            sectorNameSpan.textContent = sectorName;
            
            // Imposta l'URL dell'action del form
            form.action = "{{ route('admin.job-titles.sectors.update', [$title, ':sectorId']) }}".replace(':sectorId', sectorId);
            
            // Imposta il valore corrente nel select
            riskSelect.value = currentRisk;
            
            // Apri il modal
            modal.showModal();
        }
    </script>
</x-layouts.admin>
