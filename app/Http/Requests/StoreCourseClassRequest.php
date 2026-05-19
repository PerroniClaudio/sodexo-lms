<?php

namespace App\Http\Requests;

use App\Models\Course;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCourseClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->course()?->supportsClasses() ?? false;
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
            'starts_at_date' => ['required', 'date_format:Y-m-d'],
            'starts_at_time' => ['required', 'date_format:H:i'],
            'ends_at_date' => ['required', 'date_format:Y-m-d'],
            'ends_at_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->startsAt()->gte($this->endsAt())) {
                    $validator->errors()->add('ends_at_time', __('La fine della classe deve essere successiva all\'inizio.'));
                }
            },
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            sprintf('%s %s', $this->string('starts_at_date'), $this->string('starts_at_time')),
        );
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            sprintf('%s %s', $this->string('ends_at_date'), $this->string('ends_at_time')),
        );
    }

    protected function course(): ?Course
    {
        return $this->route('course');
    }
}
