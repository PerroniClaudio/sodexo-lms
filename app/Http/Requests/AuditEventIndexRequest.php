<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('superadmin') ?? false;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'actor_user_id' => ['nullable', 'integer', 'exists:users,id'], 'company_division_id' => ['nullable', 'integer', 'exists:company_divisions,id'],
            'action' => ['nullable', 'string', 'max:64'], 'subject_type' => ['nullable', 'string', 'max:64'], 'subject_id' => ['nullable', 'integer'],
        ];
    }
}
