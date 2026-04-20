<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateJobUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'country' => ['required', 'string', 'max:100'],
            'region' => ['required', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Nome'),
            'description' => __('Descrizione'),
            'country' => __('Paese'),
            'region' => __('Regione'),
            'province' => __('Provincia'),
            'city' => __('Città'),
            'address' => __('Indirizzo'),
            'postal_code' => __('CAP'),
        ];
    }
}
