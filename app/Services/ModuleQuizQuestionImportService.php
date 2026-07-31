<?php

namespace App\Services;

use App\Models\Module;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;

class ModuleQuizQuestionImportService
{
    private const HEADERS = [
        'Testo della domanda',
        'Punti che da la domanda',
        'Testo risposta corretta',
    ];

    public function import(Module $module, string $path): int
    {
        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        } catch (SpreadsheetReaderException) {
            throw ValidationException::withMessages(['file' => __('Il file Excel non è leggibile.')]);
        }
        $headers = array_map(fn (mixed $value): string => trim((string) $value), array_slice(array_shift($rows) ?? [], 0, 3));

        if ($headers !== self::HEADERS) {
            throw ValidationException::withMessages(['file' => __('Le prime tre colonne non corrispondono al template.')]);
        }

        return DB::transaction(function () use ($module, $rows): int {
            $imported = 0;

            foreach ($rows as $index => $row) {
                if (collect($row)->every(fn (mixed $value): bool => trim((string) $value) === '')) {
                    continue;
                }

                $this->importRow($module, $row, $index + 2);
                $imported++;
            }

            if ($imported === 0) {
                throw ValidationException::withMessages(['file' => __('Il file non contiene domande da importare.')]);
            }

            $module->updateQuizMaxScore();

            return $imported;
        });
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function importRow(Module $module, array $row, int $rowNumber): void
    {
        $questionText = trim((string) ($row[0] ?? ''));
        $points = $row[1] ?? null;
        $answers = collect(array_slice($row, 2))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->values();

        if ($questionText === '' || filter_var($points, FILTER_VALIDATE_INT) === false || (int) $points < 1 || $answers->count() < 2) {
            throw ValidationException::withMessages([
                'file' => __('Riga :row: inserisci domanda, punti interi positivi, risposta corretta e almeno una risposta alternativa.', ['row' => $rowNumber]),
            ]);
        }

        $question = $module->quizQuestions()->create(['text' => $questionText, 'points' => (int) $points]);
        $correctAnswer = $question->answers()->create(['text' => $answers->shift()]);
        $question->answers()->createMany($answers->map(fn (string $text): array => ['text' => $text])->all());
        $question->update(['correct_answer_id' => $correctAnswer->getKey()]);
    }
}
