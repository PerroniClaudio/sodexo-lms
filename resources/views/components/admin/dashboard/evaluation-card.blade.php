@props(['evaluation'])

<div class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body gap-5">
        <h2 class="card-title">
            <x-lucide-clipboard-check class="h-5 w-5" />
            {{ __('Valutazione') }}
        </h2>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('Superano il quiz finale senza esaurire i tentativi') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $evaluation['passed_without_exhausting_attempts_percentage'] }}%</p>
            </div>
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('Utenti che riescono senza consumare tutti i tentativi') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $evaluation['passed_without_exhausting_attempts_count'] }}</p>
            </div>
            <div class="rounded-box bg-base-200 px-4 py-3">
                <p class="text-sm text-base-content/70">{{ __('Totale iscritti con quiz finale') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $evaluation['final_quiz_enrollments_count'] }}</p>
            </div>
        </div>

        <progress
            class="progress progress-primary w-full"
            value="{{ $evaluation['passed_without_exhausting_attempts_percentage'] }}"
            max="100"
        ></progress>

        <div class="space-y-3">
            @forelse ($evaluation['course_breakdown'] as $course)
                <div class="flex items-center justify-between gap-4 rounded-box bg-base-200 px-4 py-3">
                    <div>
                        <p class="font-medium">{{ $course['course_title'] }}</p>
                        <p class="text-xs text-base-content/60">
                            {{ __(':passed su :total iscritti passano senza esaurire i tentativi', [
                                'passed' => $course['passed_without_exhausting_attempts_count'],
                                'total' => $course['enrolled_users_count'],
                            ]) }}
                        </p>
                    </div>
                    <span class="badge badge-primary h-fit">{{ $course['percentage'] }}%</span>
                </div>
            @empty
                <p class="text-sm text-base-content/60">{{ __('Nessun corso con quiz finale disponibile.') }}</p>
            @endforelse
        </div>
    </div>
</div>
