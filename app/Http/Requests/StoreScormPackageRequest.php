<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreScormPackageRequest extends FormRequest
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
            'package' => [
                'required',
                'file',
                File::types(['zip'])->max(1024 * 100),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'package' => __('Pacchetto SCORM'),
            'title' => __('Titolo personalizzato'),
            'description' => __('Descrizione personalizzata'),
        ];
    }
}
