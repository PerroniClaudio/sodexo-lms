<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'global_risk_level' => ['nullable', 'in:low,medium,high'],
            'global_sector_risk_override' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('Nome'),
            'code' => __('Codice'),
            'description' => __('Descrizione'),
            'global_risk_level' => __('Livello di rischio globale'),
            'global_sector_risk_override' => __('Override rischio settore'),
        ];
    }
}
