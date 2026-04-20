<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('job_levels', 'code')->ignore($this->route('job_level'))],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => __('Nome'),
            'code' => __('Codice'),
            'description' => __('Descrizione'),
        ];
    }
}
