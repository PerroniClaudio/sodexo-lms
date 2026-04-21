<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScormRuntimeValueRequest extends FormRequest
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
        $scalarValueRule = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== null && ! is_scalar($value)) {
                $fail(__('Il campo :attribute deve contenere un valore scalare.', ['attribute' => $attribute]));
            }
        };

        return [
            'session_id' => ['required', 'string', 'max:120'],
            'sco_identifier' => ['required', 'string', 'max:255'],
            'element' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', $scalarValueRule],
            'values' => ['sometimes', 'array'],
            'values.*' => ['nullable', $scalarValueRule],
            'code' => ['sometimes', 'string', 'max:10'],
        ];
    }
}
