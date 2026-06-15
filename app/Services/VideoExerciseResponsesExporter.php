<?php

namespace App\Services;

use App\Models\VideoExercise;
use App\Models\VideoExerciseAnswer;
use App\Models\VideoExerciseSubmission;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class VideoExerciseResponsesExporter
{
    public function buildWorkbook(VideoExercise $videoExercise): Spreadsheet
    {
        $videoExercise->loadMissing([
            'submissions' => fn ($query) => $query
                ->with(['user', 'answers.question'])
                ->orderBy('user_id'),
        ]);

        $spreadsheet = new Spreadsheet;
        $sheets = $videoExercise->submissions;

        if ($sheets->isEmpty()) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Risposte');
            $sheet->setCellValue('A1', 'Nessuna risposta presente.');

            return $spreadsheet;
        }

        $usedTitles = [];

        foreach ($sheets->values() as $index => $submission) {
            $sheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle($this->uniqueSheetTitle($submission, $usedTitles));
            $this->fillSubmissionSheet($sheet, $submission);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function buildWorkbookContents(VideoExercise $videoExercise): string
    {
        $spreadsheet = $this->buildWorkbook($videoExercise);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'video-exercise-responses-');

        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException('Unable to create temporary video exercise responses export file.');
        }

        try {
            (new Xlsx($spreadsheet))->save($temporaryFile);
            $contents = file_get_contents($temporaryFile);

            if ($contents === false) {
                throw new RuntimeException('Unable to read temporary video exercise responses export file.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    private function fillSubmissionSheet(Worksheet $sheet, VideoExerciseSubmission $submission): void
    {
        $user = $submission->user;
        $rows = [
            ['UID', $user?->getKey(), null],
            ['Username', $user?->email, null],
            ['Nome', trim(($user?->surname ?? '').' '.($user?->name ?? '')), null],
            ['Inizio esercitazione', $this->formatDateTime($submission->started_at), null],
            ['Completato', $submission->status === VideoExerciseSubmission::STATUS_COMPLETED ? 'Si' : 'No', null],
            ['Data completamento', $this->formatDateTime($submission->completed_at), null],
            ['Tempo trascorso', $this->formatElapsedSeconds($submission->elapsed_seconds), null],
            [null, null, null],
            ['Question ID', 'Domanda', 'Risposta'],
        ];

        /** @var Collection<int, VideoExerciseAnswer> $answers */
        $answers = $submission->answers->sortBy('video_exercise_question_id')->values();

        foreach ($answers as $answer) {
            $rows[] = [
                $answer->video_exercise_question_id,
                $answer->question?->text,
                $answer->answer_text,
            ];
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
            }
        }

        $sheet->getStyle('A9:C9')->getFont()->setBold(true);
        $sheet->getStyle('A1:A9')->getFont()->setBold(true);
        $sheet->getStyle('B1:C'.max(count($rows), 9))->getAlignment()->setWrapText(true);
        $sheet->freezePane('A9');

        for ($column = 1; $column <= 3; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))
                ->setWidth($this->columnWidth($rows, $column - 1));
        }
    }

    /**
     * @param  array<int, string>  $usedTitles
     */
    private function uniqueSheetTitle(VideoExerciseSubmission $submission, array &$usedTitles): string
    {
        $baseTitle = 'UID_'.$submission->user_id.'_'.Str::limit((string) ($submission->user?->email ?? 'utente'), 20, '');
        $baseTitle = preg_replace('/[\\\\\\/?*:\\[\\]]/', '_', $baseTitle) ?: 'Risposte';
        $baseTitle = Str::limit($baseTitle, 31, '');
        $title = $baseTitle;
        $suffix = 1;

        while (in_array($title, $usedTitles, true)) {
            $tail = '_'.$suffix;
            $title = Str::limit($baseTitle, 31 - strlen($tail), '').$tail;
            $suffix++;
        }

        $usedTitles[] = $title;

        return $title;
    }

    private function columnWidth(array $rows, int $index): int
    {
        $maxLength = 10;

        foreach ($rows as $row) {
            $maxLength = max($maxLength, mb_strlen((string) ($row[$index] ?? '')));
        }

        return match ($index) {
            0 => min(max($maxLength + 2, 14), 18),
            1 => min(max($maxLength + 2, 35), 90),
            default => min(max($maxLength + 2, 35), 120),
        };
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->format('d/m/Y H:i');
    }

    private function formatElapsedSeconds(int $elapsedSeconds): string
    {
        return CarbonInterval::seconds($elapsedSeconds)->cascade()->format('%H:%I:%S');
    }
}
