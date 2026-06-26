<x-layouts.admin>
    <div class="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-base-content">{{ __('Force delete iscrizioni') }}</h1>
                <p class="mt-2 text-sm text-base-content/70">
                    {{ __('Utility dev-only per eliminare definitivamente iscrizioni corso o percorso formativo rispettando le origini ancora valide.') }}
                </p>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-error mb-4">
                <x-lucide-triangle-alert class="h-5 w-5" />
                <div class="grid gap-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="alert alert-warning mb-6">
            <x-lucide-triangle-alert class="h-5 w-5" />
            <span class="text-sm">
                {{ __('Questa operazione rimuove record dal database quando non esistono altre origini valide. Se resta un\'origine attiva, aggiorna solo i flag dell\'iscrizione.') }}
            </span>
        </div>

        <div class="grid gap-4">
            <div class="card border border-error/30 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="card-title">{{ __('Force delete iscrizione corso') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Accetta un ID di course_user. Se l\'iscrizione resta valida tramite un percorso formativo, viene mantenuta e viene rimossa solo l\'origine diretta.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.development-tools.force-delete-enrollments.store') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                        @csrf
                        <input type="hidden" name="target_type" value="course">
                        <label class="form-control">
                            <span class="label-text">{{ __('ID course_user') }}</span>
                            <input type="number" name="target_id" min="1" required class="input input-bordered w-full" placeholder="456" />
                        </label>
                        <button type="submit" class="btn btn-error" onclick="return confirm('{{ __('Confermi il force delete dell\'iscrizione corso selezionata?') }}')">
                            {{ __('Force delete corso') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border border-error/30 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="card-title">{{ __('Force delete iscrizione percorso formativo') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Accetta un ID di training_path_user. Elimina definitivamente l\'iscrizione al percorso e aggiorna o rimuove le iscrizioni corso collegate in base alle origini residue.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.development-tools.force-delete-enrollments.store') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                        @csrf
                        <input type="hidden" name="target_type" value="training_path">
                        <label class="form-control">
                            <span class="label-text">{{ __('ID training_path_user') }}</span>
                            <input type="number" name="target_id" min="1" required class="input input-bordered w-full" placeholder="789" />
                        </label>
                        <button type="submit" class="btn btn-error" onclick="return confirm('{{ __('Confermi il force delete dell\'iscrizione al percorso formativo selezionata?') }}')">
                            {{ __('Force delete percorso') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
