<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFundingEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'fiscal_code' => ['nullable', 'string', 'max:32'],
            'pec' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_name' => __('Ragione Sociale'),
            'vat_number' => __('Partita IVA'),
            'fiscal_code' => __('Codice Fiscale'),
            'pec' => __('PEC'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vat_number' => $this->filled('vat_number') ? $this->string('vat_number')->toString() : null,
            'fiscal_code' => $this->filled('fiscal_code') ? $this->string('fiscal_code')->toString() : null,
            'pec' => $this->filled('pec') ? $this->string('pec')->toString() : null,
        ]);
    }
}
