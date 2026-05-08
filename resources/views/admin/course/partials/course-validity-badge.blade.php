@php
    $courseIsValid = $courseValidator->validate($course);
    $courseIsPublishable = $courseValidator->isPublishable($course);
    $courseValidationErrors = $courseValidator->getValidationErrors($course);
    $coursePublishabilityErrors = $courseValidator->getPublishabilityErrors($course);
@endphp

<div class="flex flex-col gap-2">
    <div class="flex items-center gap-3">
        @if ($courseIsValid)
            <span class="badge badge-sm badge-success">{{ __('Valido') }}</span>
        @else
            <span class="badge badge-sm badge-error whitespace-nowrap">{{ __('Non valido') }}</span>
        @endif
        
        @if ($courseIsPublishable)
          @if ($course->status === 'published')
              <span class="badge badge-sm badge-success">{{ __('Pubblicato') }}</span>
          @else
            <span class="badge badge-sm badge-info">{{ __('Pubblicabile') }}</span>
          @endif
        @elseif ($courseIsValid)
            <span class="badge badge-sm badge-warning">{{ __('Non pubblicabile') }}</span>
        @endif
    </div>
    
    @if (!$courseIsValid && !empty($courseValidationErrors))
        <div class="text-xs text-error">
            <strong>{{ __('Errori di validità:') }}</strong>
            <ul class="list-disc pl-4 mt-1">
                @foreach ($courseValidationErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    @if ($courseIsValid && !$courseIsPublishable && !empty($coursePublishabilityErrors))
        <div class="text-xs text-warning">
            <strong>{{ __('Errori di pubblicabilità:') }}</strong>
            <ul class="list-disc pl-4 mt-1">
                @foreach ($coursePublishabilityErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
