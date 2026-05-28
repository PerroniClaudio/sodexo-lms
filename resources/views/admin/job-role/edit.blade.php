<x-layouts.admin>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="__('Modifica ruolo')">
            <x-slot:actions>
                @if($role->trashed())
                    <form method="POST" action="{{ route('admin.job-roles.restore', $role->id) }}" class="inline">
                        @csrf
                        <button type="submit" class="btn btn-success btn-outline">
                            <x-lucide-refresh-cw class="h-4 w-4" />
                            <span>{{ __('Ripristina ruolo') }}</span>
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.job-roles.destroy', $role) }}" onsubmit="return confirm('{{ __('Sei sicuro di voler eliminare questo ruolo?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-error btn-outline">
                            <x-lucide-trash-2 class="h-4 w-4" />
                            <span>{{ __('Elimina ruolo') }}</span>
                        </button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div>
                    <h2 class="card-title">{{ __('Dati anagrafici') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Gestisci i ruoli.') }}
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.job-roles.update', $role) }}" class="flex flex-col gap-6">
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
                                value="{{ old('name', $role->name) }}"
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
                        >{{ old('description', $role->description) }}</textarea>
                        @error('description')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('admin.job-roles.index') }}" class="btn btn-ghost">
                            {{ __('Annulla') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <span>{{ __('Salva modifiche') }}</span>
                            <x-lucide-check class="h-4 w-4" />
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="card-title">{{ __('Associazioni Settore-Rischio') }}</h2>
                        <p class="text-sm text-base-content/70">
                            {{ __('Specifica il livello di rischio di questo ruolo per settore specifico, se differisce dal rischio nativo del settore.') }}
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.job-roles.sectors.attach', $role) }}" class="flex flex-col gap-4 rounded-lg border border-base-300 bg-base-50 p-4">
                    @csrf
                    <h3 class="font-medium">{{ __('Aggiungi nuova associazione') }}</h3>

                    <div class="grid gap-4 md:grid-cols-2">
                        <x-searchable-select
                            name="job_sector_id"
                            id="job_sector_id"
                            :required="true"
                            :selected-value="old('job_sector_id')"
                            :options="$allSectors->map(fn ($sector) => ['value' => (string) $sector->id, 'label' => $sector->name, 'search' => $sector->name])->values()->all()"
                            :label="__('Settore')"
                            :placeholder="__('Cerca o seleziona un settore...')"
                        />

                        <div class="form-control flex flex-col gap-2">
                            <label for="role_risk_level" class="label p-0">
                                <span class="label-text font-medium">{{ __('Livello di rischio') }}</span>
                            </label>
                            <select
                                id="role_risk_level"
                                name="role_risk_level"
                                class="select select-bordered w-full @error('role_risk_level') select-error @enderror"
                                required
                            >
                                <option value="">{{ __('Seleziona il rischio') }}</option>
                                <option value="low">{{ __('Basso') }}</option>
                                <option value="medium">{{ __('Medio') }}</option>
                                <option value="high">{{ __('Alto') }}</option>
                            </select>
                            @error('role_risk_level')
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

                @if($role->jobSectors->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>{{ __('Settore') }}</th>
                                    <th>{{ __('Rischio settore') }}</th>
                                    <th>{{ __('Rischio ruolo') }}</th>
                                    <th>{{ __('Rischio effettivo') }}</th>
                                    <th>{{ __('Azioni') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($role->jobSectors as $sector)
                                    @php
                                        $sectorRisk = $sector->getRiskLevel();
                                        $roleRisk = \App\Enums\RiskLevel::from($sector->pivot->role_risk_level);
                                        $effectiveRisk = $sector->getEffectiveWorkerRisk($role->id);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="font-medium">{{ $sector->name }}</div>
                                            <div class="text-xs text-base-content/60">
                                                {{ $sector->description }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $sectorRisk->badgeColor() }}">
                                                {{ $sectorRisk->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $roleRisk->badgeColor() }}">
                                                {{ $roleRisk->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $effectiveRisk->badgeColor() }}">
                                                {{ $effectiveRisk->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-primary btn-sm"
                                                    onclick="openEditRiskModal({{ $sector->id }}, '{{ $sector->name }}', '{{ $roleRisk->value }}')"
                                                >
                                                    <x-lucide-edit class="h-4 w-4" />
                                                </button>
                                                <form method="POST" action="{{ route('admin.job-roles.sectors.detach', [$role, $sector]) }}" class="inline">
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

    <dialog id="editRiskModal" class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-bold">{{ __('Modifica rischio ruolo') }}</h3>
            <p class="py-4 text-sm text-base-content/70">
                {{ __('Modifica il livello di rischio per') }} <span id="modalSectorName" class="font-medium"></span>
            </p>

            <form id="editRiskForm" method="POST" action="">
                @csrf
                @method('PUT')

                <div class="form-control flex flex-col gap-2">
                    <label for="modal_role_risk_level" class="label p-0">
                        <span class="label-text font-medium">{{ __('Livello di rischio') }}</span>
                    </label>
                    <select
                        id="modal_role_risk_level"
                        name="role_risk_level"
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
            const riskSelect = document.getElementById('modal_role_risk_level');

            sectorNameSpan.textContent = sectorName;
            form.action = "{{ route('admin.job-roles.sectors.update', [$role, ':sectorId']) }}".replace(':sectorId', sectorId);
            riskSelect.value = currentRisk;
            modal.showModal();
        }
    </script>
</x-layouts.admin>
