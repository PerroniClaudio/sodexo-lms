<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file_path' => ['nullable', 'string', 'max:2048'],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'internal_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'is_internal' => ['nullable', 'boolean'],
            'risk_based_requirement_ids' => ['nullable', 'array'],
            'risk_based_requirement_ids.*' => ['integer', 'exists:risk_based_requirements,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('nome attestato'),
            'description' => __('descrizione'),
            'file_path' => __('percorso file'),
            'issued_at' => __('data conseguimento'),
            'expires_at' => __('data scadenza'),
            'internal_course_id' => __('corso interno'),
            'risk_based_requirement_ids' => __('requisiti di rischio'),
        ];
    }
}
