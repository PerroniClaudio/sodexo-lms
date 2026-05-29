<?php

namespace App\Http\Requests;

use App\Enums\RiskLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiskBasedRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isLimitedValidity = $this->boolean('is_limited_validity');

        if ($isLimitedValidity) {
            $years = (int) $this->input('validity_years', 0);
            $months = (int) $this->input('validity_months_part', 0);
            $totalMonths = ($years * 12) + $months;

            $this->merge([
                'is_limited_validity' => true,
                'validity_months' => $totalMonths > 0 ? $totalMonths : null,
            ]);

            return;
        }

        $this->merge([
            'is_limited_validity' => false,
            'validity_months' => null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
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
        ];
    }
}
