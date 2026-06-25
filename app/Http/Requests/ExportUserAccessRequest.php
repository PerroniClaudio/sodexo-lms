<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\VideoReportRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExportUserAccessRequest extends FormRequest
{
    public const SCOPE_USER = 'user';

    public const SCOPE_JOB_DIMENSION = VideoReportRequest::SCOPE_JOB_DIMENSION;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'scope_type' => ['required', Rule::in([self::SCOPE_USER, self::SCOPE_JOB_DIMENSION])],
            'user_id' => [
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === self::SCOPE_USER),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'job_dimension' => [
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === self::SCOPE_JOB_DIMENSION),
                'nullable',
                Rule::in(array_keys(VideoReportRequest::jobDimensionOptions())),
            ],
            'job_dimension_id' => [
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === self::SCOPE_JOB_DIMENSION),
                'nullable',
                'integer',
                'min:1',
            ],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->input('scope_type') === self::SCOPE_USER) {
                    $exists = User::query()->whereKey($this->integer('user_id'))->exists();

                    if (! $exists) {
                        $validator->errors()->add('user_id', __('Utente non valido.'));
                    }

                    return;
                }

                $dimension = VideoReportRequest::jobDimensionOptions()[$this->string('job_dimension')->toString()] ?? null;

                if ($dimension === null) {
                    return;
                }

                $modelClass = $dimension['model'];
                $exists = $modelClass::query()->whereKey($this->integer('job_dimension_id'))->exists();

                if (! $exists) {
                    $validator->errors()->add('job_dimension_id', __('Valore filtro lavoro non valido.'));
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->filled('user_id') ? $this->input('user_id') : null,
            'job_dimension' => $this->filled('job_dimension') ? $this->input('job_dimension') : null,
            'job_dimension_id' => $this->filled('job_dimension_id') ? $this->input('job_dimension_id') : null,
        ]);
    }
}
