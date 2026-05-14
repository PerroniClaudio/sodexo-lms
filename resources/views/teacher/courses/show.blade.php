<x-layouts.user>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="$course->title">
            <x-slot:actions>
                <a href="{{ route('teacher.courses.index') }}" class="btn btn-ghost">
                    {{ __('Torna ai corsi') }}
                </a>
            </x-slot:actions>

            {{ $course->description }}
        </x-page-header>

        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="flex flex-wrap gap-4 text-sm text-base-content/70">
                    <span>{{ __('Tipologia') }}: {{ \App\Models\Course::availableTypeLabels()[$course->type] ?? $course->type }}</span>
                    <span>{{ __('Moduli assegnati') }}: {{ $assignedModules->count() }}</span>
                </div>

                @if ($assignedModules->isEmpty())
                    <div class="rounded-box border border-dashed border-base-300 bg-base-100 p-4 text-sm text-base-content/70">
                        {{ __('Non ci sono moduli assegnati in questo corso.') }}
                    </div>
                @else
                    <div class="grid gap-4">
                        @foreach ($assignedModules as $module)
                            <div class="rounded-box border border-base-300 bg-base-100 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h2 class="text-lg font-semibold text-base-content">{{ $module->title }}</h2>
                                            <span class="badge badge-ghost">{{ \App\Models\Module::availableTypeLabels()[$module->type] ?? $module->type }}</span>
                                        </div>
                                        <p class="text-sm text-base-content/70">{{ $module->description ?: __('Nessuna descrizione disponibile.') }}</p>
                                        <p class="text-xs uppercase tracking-wide text-base-content/50">
                                            {{ __('Assegnato il :date', ['date' => $module->assigned_at_display ?? __('Data non disponibile')]) }}
                                        </p>
                                    </div>

                                    @if ($module->type === 'live')
                                        <a href="{{ route('teacher.live-stream.player', $module) }}" class="btn btn-primary btn-sm">
                                            {{ __('Apri live') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.user>
