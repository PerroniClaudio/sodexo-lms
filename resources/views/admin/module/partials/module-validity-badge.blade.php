<div id="module-validity-badge" class="flex items-center gap-3">
    @if ($isValid)
        <span class="badge badge-sm badge-success">{{ __('Valido') }}</span>
    @else
        <span class="badge badge-sm badge-error whitespace-nowrap">{{ __('Non valido') }}</span>
        @if (!empty($validationErrors))
            <span class="text-xs text-error">{{ implode(' ', $validationErrors) }}</span>
        @endif
    @endif
</div>
