<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFaviconRequest extends FormRequest
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
            'favicon' => ['bail', 'required', 'file', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $favicon = $this->file('favicon');

            if ($favicon === null || ! $favicon->isValid()) {
                return;
            }

            $signature = file_get_contents($favicon->getRealPath(), false, null, 0, 8) ?: '';

            if (str_starts_with($signature, "\x00\x00\x01\x00")) {
                return;
            }

            if ($signature === "\x89PNG\r\n\x1A\n") {
                $validator->errors()->add('favicon', __('Il file selezionato è un PNG, non una favicon ICO valida. Carica un file ICO.'));

                return;
            }

            $validator->errors()->add('favicon', __('La favicon deve essere un file .ico valido.'));
        }];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'favicon.max' => __('La favicon non può superare 100 KB.'),
        ];
    }
}
