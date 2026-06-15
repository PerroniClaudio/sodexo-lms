<?php

namespace App\Services;

use App\Models\VideoExercise;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class VideoExerciseActivityExporter
{
    public function buildWorkbook(VideoExercise $videoExercise): Spreadsheet
    {
        $videoExercise->loadMissing([
            'auditEvents' => fn ($query) => $query
                ->with('user')
                ->orderBy('occurred_at')
                ->orderBy('id'),
        ]);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attivita utenti');

        $headings = [
            'Log ID',
            'UID',
            'Username',
            'Nome',
            'Practice User ID',
            'Tipo operazione',
            'Completamento (%)',
            'Tempo trascorso',
            'Inizio esercitazione',
            'Data completamento',
            'Data operazione',
            'Ultima modifica',
        ];

        $rows = $videoExercise->auditEvents->map(fn ($event) => [
            $event->getKey(),
            $event->user?->getKey(),
            $event->user?->email,
            trim(($event->user?->surname ?? '').' '.($event->user?->name ?? '')),
            $event->video_exercise_submission_id,
            $event->event_type,
            $event->completion_percentage,
            $this->formatElapsedSeconds($event->elapsed_seconds),
            $this->formatDateTime($event->started_at),
            $this->formatDateTime($event->completed_at),
            $this->formatDateTime($event->occurred_at),
            $this->formatDateTime($event->updated_at_snapshot),
        ])->all();

        $this->fillSheet($sheet, $headings, $rows);

        return $spreadsheet;
    }

    public function buildWorkbookContents(VideoExercise $videoExercise): string
    {
        $spreadsheet = $this->buildWorkbook($videoExercise);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'video-exercise-activity-');

        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException('Unable to create temporary video exercise activity export file.');
        }

        try {
            (new Xlsx($spreadsheet))->save($temporaryFile);
            $contents = file_get_contents($temporaryFile);

            if ($contents === false) {
                throw new RuntimeException('Unable to read temporary video exercise activity export file.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function fillSheet(Worksheet $sheet, array $headings, array $rows): void
    {
        foreach ($headings as $columnIndex => $heading) {
            $sheet->setCellValue([$columnIndex + 1, 1], $heading);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
            }
        }

        $sheet->getStyle('A1:L1')->getFont()->setBold(true);

        for ($column = 1; $column <= count($headings); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))
                ->setWidth($this->columnWidth($headings, $rows, $column - 1));
        }
    }

    private function columnWidth(array $headings, array $rows, int $index): int
    {
        $maxLength = mb_strlen((string) ($headings[$index] ?? ''));

        foreach ($rows as $row) {
            $maxLength = max($maxLength, mb_strlen((string) ($row[$index] ?? '')));
        }

        return min(max($maxLength + 2, 12), 32);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function formatElapsedSeconds(?int $elapsedSeconds): ?string
    {
        if ($elapsedSeconds === null) {
            return null;
        }

        return CarbonInterval::seconds($elapsedSeconds)->cascade()->format('%H:%I:%S');
    }
}
