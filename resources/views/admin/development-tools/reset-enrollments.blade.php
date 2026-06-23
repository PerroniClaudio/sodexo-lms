<x-layouts.admin>
    <div class="mx-auto w-full max-w-5xl p-4 sm:p-6 lg:p-8">
        <div class="mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-base-content">{{ __('Reset iscrizioni') }}</h1>
                <p class="mt-2 text-sm text-base-content/70">
                    {{ __('Utility dev-only per ripristinare avanzamento di modulo, corso e percorso formativo.') }}
                </p>
            </div>
        </div>

        @php
            $resetRisk = session('reset_risk');
            $targetLabels = [
                'module' => __('modulo'),
                'course' => __('corso'),
                'training_path' => __('percorso formativo'),
            ];
        @endphp

        @if(is_array($resetRisk) && !empty($resetRisk['warnings']))
            <div class="card border border-warning/40 bg-warning/10 shadow-sm mb-6">
                <div class="card-body gap-4">
                    <h2 class="card-title text-warning-content">{{ __('Controlli di sicurezza') }}</h2>
                    <p class="text-sm text-base-content/80">
                        {{ __('Il reset richiesto per :target #:id ha evidenziato possibili impatti.', [
                            'target' => $targetLabels[$resetRisk['target_type'] ?? 'course'] ?? __('iscrizione'),
                            'id' => $resetRisk['target_id'] ?? '-',
                        ]) }}
                    </p>

                    <ul class="list-disc space-y-2 pl-5 text-sm text-base-content/80">
                        @foreach(($resetRisk['warnings'] ?? []) as $warning)
                            <li>{{ $warning['message'] ?? '' }}</li>
                        @endforeach
                    </ul>

                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('admin.development-tools.reset-enrollments.store') }}">
                            @csrf
                            <input type="hidden" name="target_type" value="{{ $resetRisk['target_type'] }}">
                            <input type="hidden" name="target_id" value="{{ $resetRisk['target_id'] }}">
                            <input type="hidden" name="force_reset" value="1">
                            <button type="submit" class="btn btn-error">
                                {{ __('Procedi comunque con il reset') }}
                            </button>
                        </form>

                        <a href="{{ route('admin.development-tools.reset-enrollments.index') }}" class="btn btn-ghost">
                            {{ __('Annulla') }}
                        </a>
                    </div>
                </div>
            </div>
        @endif

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

        <div class="grid gap-4">
            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="card-title">{{ __('Reset avanzamento modulo') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Ripristina un record in module_user (id) e blocca i moduli successivi nello stesso corso.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.development-tools.reset-enrollments.store') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                        @csrf
                        <input type="hidden" name="target_type" value="module">
                        <label class="form-control">
                            <span class="label-text">{{ __('ID module_user') }}</span>
                            <input type="number" name="target_id" min="1" required class="input input-bordered w-full" placeholder="123" />
                        </label>
                        <button type="submit" class="btn btn-warning" onclick="return confirm('{{ __('Confermi il reset del modulo selezionato?') }}')">
                            {{ __('Reset modulo') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="card-title">{{ __('Reset avanzamento corso') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Ripristina una iscrizione corso (course_user.id), azzera progressi modulo e riporta al primo modulo disponibile.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.development-tools.reset-enrollments.store') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                        @csrf
                        <input type="hidden" name="target_type" value="course">
                        <label class="form-control">
                            <span class="label-text">{{ __('ID course_user') }}</span>
                            <input type="number" name="target_id" min="1" required class="input input-bordered w-full" placeholder="456" />
                        </label>
                        <button type="submit" class="btn btn-warning" onclick="return confirm('{{ __('Confermi il reset del corso selezionato?') }}')">
                            {{ __('Reset corso') }}
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border border-base-300 bg-base-100 shadow-sm">
                <div class="card-body gap-4">
                    <h2 class="card-title">{{ __('Reset avanzamento percorso formativo') }}</h2>
                    <p class="text-sm text-base-content/70">
                        {{ __('Ripristina una iscrizione training_path_user (id) e i corsi pubblicati collegati al percorso.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.development-tools.reset-enrollments.store') }}" class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                        @csrf
                        <input type="hidden" name="target_type" value="training_path">
                        <label class="form-control">
                            <span class="label-text">{{ __('ID training_path_user') }}</span>
                            <input type="number" name="target_id" min="1" required class="input input-bordered w-full" placeholder="789" />
                        </label>
                        <button type="submit" class="btn btn-warning" onclick="return confirm('{{ __('Confermi il reset del percorso formativo selezionato?') }}')">
                            {{ __('Reset percorso') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
