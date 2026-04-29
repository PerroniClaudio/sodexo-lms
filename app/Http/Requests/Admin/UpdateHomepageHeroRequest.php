<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHomepageHeroRequest extends FormRequest
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
            'background_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'content' => ['required', 'string', 'max:10000'],
            'button_enabled' => ['nullable', 'boolean'],
            'button_color' => ['required', Rule::in(['primary', 'secondary', 'accent', 'neutral'])],
            'button_text' => ['required_if_accepted:button_enabled', 'nullable', 'string', 'max:80'],
            'button_url' => ['required_if_accepted:button_enabled', 'nullable', 'string', 'max:2048'],
        ];
    }
}
