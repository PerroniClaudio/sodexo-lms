<?php

namespace App\Http\Requests;

use App\Enums\RiskLevel;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobSectorRequest extends FormRequest
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
            'employer_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn (Builder $query): Builder => $query->where('job_sector_id', $this->route('job_sector')->getKey())
                ),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('Nome'),
            'description' => __('Descrizione'),
            'manual_risk_level' => __('Rischio manuale'),
            'employer_user_id' => __('Datore di lavoro'),
        ];
    }
}
