<?php

namespace App\Services\Certificates;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\User;
use Carbon\CarbonInterface;

class CertificateVariableResolver
{
    /**
     * @return array<string, string>
     */
    public function resolve(Course $course, User $user, ?CourseEnrollment $enrollment = null): array
    {
        $course->loadMissing('modules');

        $appointmentModule = $course->modules
            ->whereIn('type', ['res', 'live'])
            ->sortBy('order')
            ->first();

        return [
            '${TITOLO}' => (string) ($course->title ?? ''),
            '${ORE}' => $this->resolveHours($course),
            '${NOME_UTENTE}' => (string) ($user->name ?? ''),
            '${COGNOME_UTENTE}' => (string) ($user->surname ?? ''),
            '${CODICE_FISCALE_UTENTE}' => (string) ($user->fiscal_code ?? ''),
            '${DATA_COMPLETAMENTO_CORSO}' => $this->formatDate($enrollment?->completed_at ?? today()),
            '${DATA_CORSO}' => $this->formatDate($appointmentModule?->appointment_start_time ?? today()),
            '${ORARIO_CORSO}' => $this->resolveCourseTime($appointmentModule),
        ];
    }

    private function resolveHours(Course $course): string
    {
        $minutes = $course->modules
            ->whereIn('type', ['res', 'live'])
            ->sum(function (Module $module): int {
                if ($module->appointment_start_time === null || $module->appointment_end_time === null) {
                    return 0;
                }

                return max(0, $module->appointment_start_time->diffInMinutes($module->appointment_end_time));
            });

        if ($minutes === 0) {
            return '';
        }

        $hours = $minutes / 60;

        if (fmod($hours, 1.0) === 0.0) {
            return (string) (int) $hours;
        }

        return number_format($hours, 2, ',', '');
    }

    private function resolveCourseTime(?Module $module): string
    {
        if ($module?->appointment_start_time === null || $module->appointment_end_time === null) {
            return '';
        }

        return sprintf(
            '%s - %s',
            $module->appointment_start_time->format('H:i'),
            $module->appointment_end_time->format('H:i')
        );
    }

    private function formatDate(CarbonInterface $date): string
    {
        return $date->format('d/m/Y');
    }
}
