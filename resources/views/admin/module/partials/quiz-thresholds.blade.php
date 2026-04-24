<div class="grid gap-6 md:grid-cols-2">
    <div class="grid gap-2">
        <label for="passing_score" class="label p-0">
            <span class="label-text font-medium">{{ __('Passing score') }}</span>
        </label>
        <input
            id="passing_score"
            name="passing_score"
            type="number"
            min="0"
            value="{{ old('passing_score', $module->passing_score) }}"
            class="input input-bordered w-full @error('passing_score') input-error @enderror"
        >
        @error('passing_score')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-2">
        <label for="max_score" class="label p-0">
            <span class="label-text font-medium">@if($module->isQuiz()){{ __('Maximum score (auto)') }}@else{{ __('Maximum score') }}@endif</span>
        </label>
        <input
            id="max_score"
            name="max_score"
            type="number"
            min="1"
            value="{{ old('max_score', $module->max_score) }}"
            class="input input-bordered w-full @error('max_score') input-error @enderror"
            @if($module->isQuiz()) disabled @endif
        >
        @error('max_score')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>
</div>
