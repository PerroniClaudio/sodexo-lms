<?php

namespace App\Http\Requests;

use App\Models\Module;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ConfirmLiveAttendanceRequest extends FormRequest
{
    protected ?Module $module = null;

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
            'effective_start_time' => ['required', 'date_format:H:i'],
            'effective_end_time' => ['required', 'date_format:H:i'],
            'minimum_attendance_percentage' => ['required', 'integer', 'min:1', 'max:100'],
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

                $module = $this->module();
                $referenceDate = $module?->appointment_start_time?->format('Y-m-d')
                    ?? $module?->appointment_date?->format('Y-m-d');

                if ($referenceDate === null) {
                    $validator->errors()->add('effective_start_time', __('Il modulo live non ha una data di riferimento valida.'));

                    return;
                }

                $effectiveStartAt = CarbonImmutable::createFromFormat(
                    'Y-m-d H:i',
                    sprintf('%s %s', $referenceDate, $this->validated('effective_start_time')),
                );
                $effectiveEndAt = CarbonImmutable::createFromFormat(
                    'Y-m-d H:i',
                    sprintf('%s %s', $referenceDate, $this->validated('effective_end_time')),
                );

                if ($effectiveEndAt->lessThanOrEqualTo($effectiveStartAt)) {
                    $validator->errors()->add('effective_end_time', __('L\'ora di fine effettiva deve essere successiva all\'ora di inizio effettiva.'));
                }
            },
        ];
    }

    public function effectiveStartAt(): CarbonImmutable
    {
        return $this->effectiveDateTime($this->validated('effective_start_time'));
    }

    public function effectiveEndAt(): CarbonImmutable
    {
        return $this->effectiveDateTime($this->validated('effective_end_time'));
    }

    public function minimumAttendancePercentage(): int
    {
        return (int) $this->validated('minimum_attendance_percentage');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'effective_start_time' => __('ora di inizio effettiva'),
            'effective_end_time' => __('ora di fine effettiva'),
            'minimum_attendance_percentage' => __('percentuale minima di presenza'),
        ];
    }

    protected function module(): ?Module
    {
        return $this->module ??= $this->route('module');
    }

    private function effectiveDateTime(string $time): CarbonImmutable
    {
        $referenceDate = $this->module()?->appointment_start_time?->format('Y-m-d')
            ?? $this->module()?->appointment_date?->format('Y-m-d')
            ?? now()->format('Y-m-d');

        return CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            sprintf('%s %s', $referenceDate, $time),
        );
    }
}
