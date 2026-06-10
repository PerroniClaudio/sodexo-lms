@props([
    'followUpUsers',
    'followUpInactiveDays',
])

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="card-title">
                    <x-lucide-bell-ring class="h-5 w-5" />
                    {{ __('Utenti da sollecitare') }}
                </h2>
                <p class="text-sm text-base-content/70">{{ __('Utenti con corsi iniziati ma non conclusi.') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('admin.dashboard') }}" class="flex items-center gap-2">
                    <label for="inactive_days" class="text-sm text-base-content/70">{{ __('Inattivi da') }}</label>
                    <select id="inactive_days" name="inactive_days" class="select select-bordered select-sm">
                        <option value="">{{ __('Tutti') }}</option>
                        @foreach ([7, 15, 30] as $days)
                            <option value="{{ $days }}" @selected($followUpInactiveDays === $days)>{{ $days }} {{ __('giorni') }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline">{{ __('Filtra') }}</button>
                </form>

                <a
                    href="{{ route('admin.dashboard.follow-up-users.export', array_filter(['inactive_days' => $followUpInactiveDays])) }}"
                    class="btn btn-sm btn-primary"
                >
                    {{ __('Esporta CSV') }}
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-zebra">
                <thead>
                    <tr>
                        <th>{{ __('Utente') }}</th>
                        <th class="text-right">{{ __('Corsi aperti') }}</th>
                        <th>{{ __('Ultimo accesso') }}</th>
                        <th>{{ __('Corso aperto più vecchio') }}</th>
                        <th class="text-right">{{ __('Avanzamento medio') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($followUpUsers->take(10) as $followUpUser)
                        <tr>
                            <td>
                                <div class="font-medium">{{ $followUpUser['full_name'] }}</div>
                                <div class="text-xs text-base-content/60">{{ $followUpUser['email'] }}</div>
                            </td>
                            <td class="text-right font-semibold">{{ $followUpUser['open_courses_count'] }}</td>
                            <td>{{ $followUpUser['last_accessed_at_label'] }}</td>
                            <td>
                                <div>{{ $followUpUser['oldest_open_course_title'] }}</div>
                                <div class="text-xs text-base-content/60">{{ __('Dal :date', ['date' => $followUpUser['oldest_open_course_started_at_label']]) }}</div>
                            </td>
                            <td class="text-right">{{ $followUpUser['average_completion_percentage'] }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-base-content/60">{{ __('Nessun utente da sollecitare con i filtri correnti.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
