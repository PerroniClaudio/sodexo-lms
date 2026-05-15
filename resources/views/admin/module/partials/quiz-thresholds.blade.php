{{-- <div class="grid gap-6 md:grid-cols-2"> --}}
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
{{-- </div> --}}

@if($module->type === 'learning_quiz')
    <div class="grid gap-2">
        <label for="max_attempts" class="label p-0">
            <span class="label-text font-medium">{{ __('Tentativi massimi') }} <span class="text-error">*</span></span>
        </label>
        <input
            id="max_attempts"
            name="max_attempts"
            type="number"
            min="1"
            required
            value="{{ old('max_attempts', $module->max_attempts) }}"
            class="input input-bordered w-full @error('max_attempts') input-error @enderror"
        >
        {{-- <span class="text-sm text-base-content/70">{{ __('Numero massimo di tentativi permessi per questo quiz') }}</span> --}}
        @error('max_attempts')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-2">
        <label for="permitted_submission" class="label p-0">
            <span class="label-text font-medium">{{ __('Modalità') }} <span class="text-error">*</span></span>
        </label>
        <select
            id="permitted_submission"
            name="permitted_submission"
            class="select select-bordered w-full @error('permitted_submission') select-error @enderror"
            required
        >
            @foreach(\App\Models\Module::availablePermittedSubmissionLabels() as $value => $label)
                <option value="{{ $value }}" @selected(old('permitted_submission', $module->permitted_submission ?? 'online') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('permitted_submission')
            <p class="text-sm text-error">{{ $message }}</p>
        @enderror
    </div>
@endif
