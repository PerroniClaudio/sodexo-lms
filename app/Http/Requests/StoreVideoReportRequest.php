<?php

namespace App\Http\Requests;

use App\Models\VideoReportRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVideoReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'scope_type' => ['required', Rule::in(VideoReportRequest::SCOPES)],
            'course_id' => [
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === VideoReportRequest::SCOPE_COURSE),
                'nullable',
                'integer',
                'exists:courses,id',
            ],
            'job_dimension' => [
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === VideoReportRequest::SCOPE_JOB_DIMENSION),
                'nullable',
                Rule::in(array_keys(VideoReportRequest::jobDimensionOptions())),
            ],
            'job_dimension_id' => [
                Rule::requiredIf(fn (): bool => $this->input('scope_type') === VideoReportRequest::SCOPE_JOB_DIMENSION),
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

                if ($this->input('scope_type') !== VideoReportRequest::SCOPE_JOB_DIMENSION) {
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
            'course_id' => $this->filled('course_id') ? $this->input('course_id') : null,
            'job_dimension' => $this->filled('job_dimension') ? $this->input('job_dimension') : null,
            'job_dimension_id' => $this->filled('job_dimension_id') ? $this->input('job_dimension_id') : null,
        ]);
    }
}
