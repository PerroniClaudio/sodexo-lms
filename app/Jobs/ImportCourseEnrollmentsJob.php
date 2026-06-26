<?php

namespace App\Jobs;

use App\Models\Importazione;
use App\Services\CourseEnrollmentImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportCourseEnrollmentsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $importazioneId) {}

    public function handle(CourseEnrollmentImportService $courseEnrollmentImportService): void
    {
        $importazione = Importazione::query()->findOrFail($this->importazioneId);
        $disk = Storage::disk(Importazione::STORAGE_DISK);

        if (! $disk->exists($importazione->file_path)) {
            $importazione->update([
                'status' => Importazione::STATUS_FAILED,
                'started_at' => now(),
                'finished_at' => now(),
                'error_message' => __('File import non trovato.'),
            ]);

            return;
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'user-courses-import-');

        if ($temporaryFile === false) {
            throw new \RuntimeException('Impossibile creare file temporaneo per import associazione utenti corsi.');
        }

        $importazione->update([
            'status' => Importazione::STATUS_PROGRESS,
            'started_at' => now(),
            'finished_at' => null,
            'error_message' => null,
        ]);

        file_put_contents($temporaryFile, $disk->get($importazione->file_path));

        try {
            $courseEnrollmentImportService->import($importazione, $temporaryFile);

            $importazione->update([
                'status' => Importazione::STATUS_FINISHED,
                'finished_at' => now(),
                'error_message' => null,
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

    private function formatError(\Throwable $throwable): string
    {
        if ($throwable instanceof ValidationException) {
            return Str::limit(collect($throwable->errors())->flatten()->implode(' '), 60000);
        }

        return Str::limit($throwable->getMessage(), 60000);
    }
}
