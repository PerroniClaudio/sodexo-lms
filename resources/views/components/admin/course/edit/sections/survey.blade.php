@props([
    'activeSatisfactionSurveyTemplate',
    'courseBaseValues',
    'courseValidator',
    'updateUrl',
])

<form method="POST" action="{{ $updateUrl }}" class="flex flex-col gap-6">
    @include('admin.course.partials.course-edit-badge-bar')

    <div class="card border border-base-300 bg-base-100 shadow-sm">
        <div class="card-body gap-6">
            <div>
                <h2 class="card-title">{{ __('Questionario di gradimento') }}</h2>
                <p class="text-sm text-base-content/70">
                    {{ __('Se abilitato, viene aggiunto automaticamente come ultimo modulo del corso.') }}
                </p>
            </div>

            @csrf
            @method('PUT')

            <div class="rounded-box border border-base-300 bg-base-200/40 p-4">
                <div class="flex flex-col gap-4">
                    <label class="label cursor-pointer justify-start gap-3 p-0">
                        <input
                            type="checkbox"
                            name="has_satisfaction_survey"
                            value="1"
                            class="checkbox"
                            @checked($courseBaseValues['has_satisfaction_survey'])
                            data-satisfaction-enabled
                        >
                        <span class="label-text">{{ __('Includi questionario di gradimento') }}</span>
                    </label>
                    @error('has_satisfaction_survey')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror

                    <label class="label cursor-pointer justify-start gap-3 p-0">
                        <input
                            type="checkbox"
                            name="satisfaction_survey_required_for_certificate"
                            value="1"
                            class="checkbox"
                            @checked($courseBaseValues['satisfaction_survey_required_for_certificate'])
                            data-satisfaction-required
                        >
                        <span class="label-text">{{ __('Rendi il questionario obbligatorio per l\'ottenimento dell\'attestato') }}</span>
                    </label>
                    @error('satisfaction_survey_required_for_certificate')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror

                    <div class="text-sm text-base-content/70">
                        @role('superadmin')
                            @if ($activeSatisfactionSurveyTemplate)
                                <a href="{{ route('admin.satisfaction-survey.edit') }}" class="link link-primary">
                                    {{ __('Configura domande e risposte globali del questionario') }}
                                </a>
                            @else
                                <a href="{{ route('admin.satisfaction-survey.edit') }}" class="link link-error">
                                    {{ __('Configura prima il questionario globale di gradimento') }}
                                </a>
                            @endif
                        @else
                        @endrole
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary">
                    <span>{{ __('Salva dati') }}</span>
                    <x-lucide-save class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</form>