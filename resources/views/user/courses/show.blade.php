<x-layouts.user>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
        <x-page-header :title="$course->title" />
        <div class="card border border-base-300 bg-base-100 shadow-sm">
            <div class="card-body gap-6">
                <div class="mb-4">
                    <h2 class="text-lg font-semibold mb-2">{{ __('Descrizione') }}</h2>
                    <p class="text-base-content/80">{{ $course->description }}</p>
                </div>
                <div class="mb-4">
                    <h2 class="text-lg font-semibold mb-2">{{ __('Avanzamento corso') }}</h2>
                    <progress class="progress progress-primary w-full" value="{{ $enrollment->completion_percentage }}" max="100"></progress>
                    <span class="text-xs">{{ $enrollment->completion_percentage }}%</span>
                </div>
                @if($enrollment->currentModule && $enrollment->status !== 'completed')
                    <div class="flex justify-end">
                        <a href="{{ route('user.courses.modules.player', [$course, $enrollment->currentModule]) }}" class="btn btn-primary">
                            {{ __('Vai al modulo corrente') }}
                        </a>
                    </div>
                @endif
                <div>
                    <h2 class="text-lg font-semibold mb-2">{{ __('Moduli') }}</h2>
                    <ul class="timeline timeline-vertical">
                        @foreach($modules as $module)
                            <li>
                                <div class="timeline-start">
                                    <span class="font-semibold">{{ $module->title }}</span>
                                </div>
                                <div class="timeline-middle">
                                    @if($module->pivot->status === 'completed')
                                        <span class="badge badge-success">{{ __('Completato') }}</span>
                                    @elseif($module->pivot->status === 'in_progress')
                                        <span class="badge badge-primary">{{ __('In corso') }}</span>
                                    @else
                                        <span class="badge badge-ghost">{{ __(ucfirst($module->pivot->status)) }}</span>
                                    @endif
                                </div>
                                <div class="timeline-end flex gap-2 items-center">
                                    @if($enrollment->current_module_id === $module->id)
                                        <span class="badge badge-info">{{ __('Modulo corrente') }}</span>
                                    @endif
                                    @if(in_array($module->pivot->status, ['completed', 'available', 'in_progress']) || ($module->pivot->status === 'failed' && $module->type === 'learning_quiz' && $module->pivot->quiz_attempts < $module->max_attempts))
                                        <a href="{{ route('user.courses.modules.player', [$course, $module]) }}" class="btn btn-sm btn-outline">
                                            {{ __('Accedi') }}
                                        </a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-layouts.user>
