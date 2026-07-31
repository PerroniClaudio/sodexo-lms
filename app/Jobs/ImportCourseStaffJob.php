<?php

namespace App\Jobs;

use App\Models\Importazione;
use App\Services\CourseStaffImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportCourseStaffJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $importazioneId) {}

    public function handle(CourseStaffImportService $courseStaffImportService): void
    {
        $importazione = Importazione::query()->findOrFail($this->importazioneId);

        if (! Storage::exists($importazione->file_path)) {
            $importazione->update([
                'status' => Importazione::STATUS_FAILED,
                'started_at' => now(),
                'finished_at' => now(),
                'error_message' => __('File import non trovato.'),
            ]);

            return;
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'course-staff-import-');

        if ($temporaryFile === false) {
            throw new \RuntimeException('Impossibile creare il file temporaneo per import docenti e tutor corsi.');
        }

        $importazione->update([
            'status' => Importazione::STATUS_PROGRESS,
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
            'summary' => null,
        ]);

        file_put_contents($temporaryFile, Storage::get($importazione->file_path));

        try {
            $summary = $courseStaffImportService->import($temporaryFile);

            $importazione->update([
                'status' => Importazione::STATUS_FINISHED,
                'finished_at' => now(),
                'summary' => $summary,
            ]);
        } catch (\Throwable $throwable) {
            $importazione->update([
                'status' => Importazione::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $this->formatError($throwable),
            ]);

            report($throwable);
        } finally {
            @unlink($temporaryFile);
        }
    }

    public function failed(?\Throwable $throwable): void
    {
        $importazione = Importazione::query()->find($this->importazioneId);

        $importazione?->update([
            'status' => Importazione::STATUS_FAILED,
            'started_at' => $importazione->started_at ?? now(),
            'finished_at' => now(),
            'error_message' => $this->formatError($throwable ?? new \RuntimeException('Import non completato.')),
        ]);
    }

    private function formatError(\Throwable $throwable): string
    {
        if ($throwable instanceof ValidationException) {
            return Str::limit(collect($throwable->errors())->flatten()->implode(' '), 60000);
        }

        return Str::limit($throwable->getMessage(), 60000);
    }
}
