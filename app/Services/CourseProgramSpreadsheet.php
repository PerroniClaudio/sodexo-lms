<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class CourseProgramSpreadsheet
{
    private const HEADERS = [
        'Argomento sessione',
        'Ora Inizio',
        'Ora fine',
        'Durata (ore:minuti)',
        'Metodologie didattiche',
    ];

    public function downloadTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Programma corso');
        $sheet->fromArray([self::HEADERS]);
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');
        $sheet->getColumnDimension('A')->setWidth(42);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(38);

        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Metodologie');
        $lookupSheet->fromArray([[__('Metodologie didattiche')]]);
        $lookupSheet->fromArray(array_map(
            static fn (string $label): array => [$label],
            array_values(Course::availableProgramTeachingMethodLabels()),
        ), null, 'A2');

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle(__('Valore non valido'));
        $validation->setError(__('Seleziona una metodologia dall’elenco.'));
        $validation->setFormula1("'Metodologie'!\$A\$2:\$A\$".(count(Course::availableProgramTeachingMethods()) + 1));
        $sheet->setDataValidation('E2:E1000', $validation);
        $lookupSheet->setSheetState($lookupSheet::SHEETSTATE_HIDDEN);

        $temporaryFile = tempnam(sys_get_temp_dir(), 'course-program-template-');
        abort_if($temporaryFile === false, 500, 'Impossibile generare il template del programma corso.');

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download($temporaryFile, 'template-import-programma-corso.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return array<int, array{starts_at: ?string, ends_at: ?string, duration_hours: int, duration_minutes: int, teaching_method: string, topic: string}>
     */
    public function import(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            if ($sheet->rangeToArray('A1:E1')[0] !== self::HEADERS) {
                throw ValidationException::withMessages([
                    'file' => __('Le intestazioni del file non corrispondono al template del programma corso.'),
                ]);
            }

            $rows = [];
            $methodKeys = array_flip(Course::availableProgramTeachingMethodLabels());

            for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                [$topic, $startsAt, $endsAt, $duration, $teachingMethod] = $sheet->rangeToArray("A{$rowNumber}:E{$rowNumber}", null, true, false)[0];

                if (collect([$topic, $startsAt, $endsAt, $duration, $teachingMethod])->every(fn (mixed $value): bool => blank($value))) {
                    continue;
                }

                [$durationHours, $durationMinutes] = $this->durationParts($duration);
                $teachingMethod = trim((string) $teachingMethod);
                $rows[] = [
                    'starts_at' => $this->time($startsAt),
                    'ends_at' => $this->time($endsAt),
                    'duration_hours' => $durationHours,
                    'duration_minutes' => $durationMinutes,
                    'teaching_method' => $methodKeys[$teachingMethod] ?? $teachingMethod,
                    'topic' => trim((string) $topic),
                ];
            }

            $spreadsheet->disconnectWorksheets();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'file' => __('Il file Excel non è leggibile. Scarica il template e riprova.'),
            ]);
        }

        $validator = Validator::make(['program_schedule' => $rows], [
            'program_schedule' => ['required', 'array', 'min:1'],
            'program_schedule.*.starts_at' => ['nullable', 'date_format:H:i'],
            'program_schedule.*.ends_at' => ['nullable', 'date_format:H:i'],
            'program_schedule.*.duration_hours' => ['required', 'integer', 'min:0'],
            'program_schedule.*.duration_minutes' => ['required', 'integer', 'between:0,59'],
            'program_schedule.*.teaching_method' => ['required', Rule::in(Course::availableProgramTeachingMethods())],
            'program_schedule.*.topic' => ['required', 'string'],
        ], [], [
            'program_schedule' => __('programma corso'),
            'program_schedule.*.starts_at' => __('ora inizio'),
            'program_schedule.*.ends_at' => __('ora fine'),
            'program_schedule.*.duration_hours' => __('durata ore'),
            'program_schedule.*.duration_minutes' => __('durata minuti'),
            'program_schedule.*.teaching_method' => __('metodologia didattica'),
            'program_schedule.*.topic' => __('argomento sessione'),
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'file' => $validator->errors()->first(),
            ]);
        }

        return $rows;
    }

    private function time(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return is_numeric($value)
            ? Date::excelToDateTimeObject((float) $value)->format('H:i')
            : trim((string) $value);
    }

    /** @return array{int, int} */
    private function durationParts(mixed $value): array
    {
        if (is_numeric($value)) {
            $minutes = (int) round((float) $value * 1440);

            return [intdiv($minutes, 60), $minutes % 60];
        }

        preg_match('/^(\d+):([0-5]\d)$/', trim((string) $value), $matches);

        return isset($matches[1]) ? [(int) $matches[1], (int) $matches[2]] : [-1, -1];
    }
}
