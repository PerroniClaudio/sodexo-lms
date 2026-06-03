<?php

namespace App\Http\Requests;

use App\Enums\RiskLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'manual_risk_level' => ['nullable', 'string', Rule::in(RiskLevel::values())],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('Nome'),
            'description' => __('Descrizione'),
            'manual_risk_level' => __('Rischio manuale'),
        ];
    }
}
