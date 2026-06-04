@php
    $courseIsValid = $courseValidator->validate($course);
    $courseIsPublishable = $courseValidator->isPublishable($course);
    $courseValidationErrors = $courseValidator->getValidationErrors($course);
    $coursePublishabilityErrors = $courseValidator->getPublishabilityErrors($course);
@endphp

<div class="flex flex-col gap-2" data-validity-details>
    <div class="flex items-center gap-3">
        @if ($courseIsValid)
            <span class="badge badge-sm badge-success">{{ __('Valido') }}</span>
        @else
            <button type="button" class="badge badge-sm badge-error whitespace-nowrap cursor-pointer" data-open-validity-details-modal>
                {{ __('Non valido') }}
            </button>
        @endif

        @if ($courseIsPublishable)
            @if ($course->status === 'published')
                <span class="badge badge-sm badge-success">{{ __('Pubblicato') }}</span>
            @else
                <span class="badge badge-sm badge-info">{{ __('Pubblicabile') }}</span>
            @endif
        @elseif ($courseIsValid)
            <button type="button" class="badge badge-sm badge-warning cursor-pointer" data-open-validity-details-modal>
                {{ __('Non pubblicabile') }}
            </button>
        @endif
    </div>

    @if ((! $courseIsValid && ! empty($courseValidationErrors)) || ($courseIsValid && ! $courseIsPublishable && ! empty($coursePublishabilityErrors)))
        <dialog class="modal" data-validity-details-modal>
            <div class="modal-box max-w-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold">{{ __('Dettagli validità corso') }}</h3>
                        <p class="text-sm text-base-content/70">
                            {{ __('Questi sono i motivi per cui il corso non è considerato valido o pubblicabile.') }}
                        </p>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm btn-circle" data-close-validity-details-modal>
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </div>

                <div class="mt-6 flex flex-col gap-4">
                    @if (! $courseIsValid && ! empty($courseValidationErrors))
                        <div class="rounded-box border border-error/30 bg-error/5 p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="badge badge-error badge-soft">{{ __('Non valido') }}</span>
                                <span class="font-medium">{{ __('Errori di validità') }}</span>
                            </div>
                            <ul class="space-y-2 text-sm text-base-content/80">
                                @foreach ($courseValidationErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($courseIsValid && ! $courseIsPublishable && ! empty($coursePublishabilityErrors))
                        <div class="rounded-box border border-warning/30 bg-warning/5 p-4">
                            <div class="mb-3 flex items-center gap-2">
                                <span class="badge badge-warning badge-soft">{{ __('Non pubblicabile') }}</span>
                                <span class="font-medium">{{ __('Errori di pubblicabilità') }}</span>
                            </div>
                            <ul class="space-y-2 text-sm text-base-content/80">
                                @foreach ($coursePublishabilityErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            <form method="dialog" class="modal-backdrop">
                <button>{{ __('Chiudi') }}</button>
            </form>
        </dialog>
    @endif
</div>
