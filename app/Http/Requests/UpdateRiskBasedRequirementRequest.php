<?php

namespace App\Http\Requests;

use App\Enums\RiskLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRiskBasedRequirementRequest extends FormRequest
{
    private const GENERAL_TRAINING = 'general';

    private const SPECIFIC_TRAINING = 'specific';

    private const SPECIFIC_PROGRESS_GROUP = 'worker_specific_training';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isLimitedValidity = $this->boolean('is_limited_validity');
        $riskProgressionGroup = $this->mappedRiskProgressionGroup();

        if ($isLimitedValidity) {
            $years = (int) $this->input('validity_years', 0);
            $months = (int) $this->input('validity_months_part', 0);
            $totalMonths = ($years * 12) + $months;

            $this->merge([
                'is_limited_validity' => true,
                'validity_months' => $totalMonths > 0 ? $totalMonths : null,
                'risk_progression_group' => $riskProgressionGroup,
            ]);

            return;
        }

        $this->merge([
            'is_limited_validity' => false,
            'validity_months' => null,
            'risk_progression_group' => $riskProgressionGroup,
        ]);
    }

    public function validatedPayload(): array
    {
        return $this->safe()
            ->except('training_family');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'training_family' => ['required', 'string', Rule::in([self::GENERAL_TRAINING, self::SPECIFIC_TRAINING])],
            'risk_progression_group' => ['nullable', 'string', 'max:100', Rule::in([self::SPECIFIC_PROGRESS_GROUP])],
            'is_limited_validity' => ['required', 'boolean'],
            'risk_levels' => ['required', 'array', 'min:1'],
            'risk_levels.*' => [Rule::enum(RiskLevel::class)],
            'validity_years' => ['nullable', 'integer', 'min:0'],
            'validity_months_part' => ['nullable', 'integer', 'min:0', 'max:11'],
            'reset_formation_years' => ['nullable', 'integer', 'min:1'],
            'validity_months' => [
                Rule::requiredIf($this->boolean('is_limited_validity')),
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('Nome'),
            'description' => __('Descrizione'),
            'training_family' => __('Famiglia formativa'),
            'risk_progression_group' => __('Gruppo progressione rischio'),
            'is_limited_validity' => __('Validita limitata'),
            'risk_levels' => __('Livelli di rischio'),
            'validity_years' => __('Anni'),
            'validity_months_part' => __('Mesi'),
            'reset_formation_years' => __('Tempo reset formazione'),
            'validity_months' => __('Validita'),
        ];
    }

    public function messages(): array
    {
        return [
            'validity_months.required_if' => 'Il campo validita e obbligatorio quando la validita e limitata.',
            'validity_months.min' => 'La validita deve essere di almeno 1 mese.',
            'risk_levels.required' => 'Devi selezionare almeno un livello di rischio.',
            'risk_levels.min' => 'Devi selezionare almeno un livello di rischio.',
            'training_family.required' => 'Devi selezionare la famiglia formativa.',
            'training_family.in' => 'La famiglia formativa selezionata non e valida.',
        ];
    }

    private function mappedRiskProgressionGroup(): ?string
    {
        return match ($this->input('training_family')) {
            self::SPECIFIC_TRAINING => self::SPECIFIC_PROGRESS_GROUP,
            default => null,
        };
    }
}
