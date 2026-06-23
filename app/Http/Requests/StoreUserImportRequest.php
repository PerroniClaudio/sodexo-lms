<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import users') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
            ],
        ];
    }
}
