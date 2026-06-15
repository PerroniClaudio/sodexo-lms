<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Esercitazioni') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('Gestisci le esercitazioni che appaiono durante il video.') }}</p>
            </div>
            <a href="{{ route('admin.courses.modules.video-exercises.create', [$course, $module]) }}" class="btn btn-primary">
                <x-lucide-plus class="h-4 w-4" />
                <span>{{ __('Nuova esercitazione') }}</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Timestamp') }}</th>
                        <th>{{ __('Tempo minimo') }}</th>
                        <th>{{ __('Domande') }}</th>
                        <th>{{ __('Materiali') }}</th>
                        <th class="text-right">{{ __('Azioni') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($module->videoExercises as $exercise)
                        <tr>
                            <td class="font-medium">{{ $exercise->title }}</td>
                            <td>{{ gmdate('H:i:s', $exercise->appears_at_seconds) }}</td>
                            <td>{{ sprintf('%02d:%02d', intdiv($exercise->minimum_seconds, 3600), intdiv($exercise->minimum_seconds % 3600, 60)) }}</td>
                            <td><span class="badge badge-outline">{{ $exercise->questions->count() }}</span></td>
                            <td><span class="badge badge-outline">{{ $exercise->materials->count() }}</span></td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.courses.modules.video-exercises.edit', [$course, $module, $exercise]) }}" class="btn btn-outline btn-sm">
                                        <x-lucide-pencil class="h-4 w-4" />
                                        <span>{{ __('Modifica') }}</span>
                                    </a>
                                    <form method="POST" action="{{ route('admin.courses.modules.video-exercises.destroy', [$course, $module, $exercise]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-error btn-outline btn-sm">
                                            <x-lucide-trash-2 class="h-4 w-4" />
                                            <span>{{ __('Elimina') }}</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-sm text-base-content/60">{{ __('Nessuna esercitazione configurata.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
