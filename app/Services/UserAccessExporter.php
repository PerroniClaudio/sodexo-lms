<?php

namespace App\Services;

use App\Http\Requests\ExportUserAccessRequest;
use App\Models\UserAccessExport;
use App\Models\VideoReportRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class UserAccessExporter
{
    /**
     * @param  array{scope_type: string, user_id?: int|null, job_dimension?: string|null, job_dimension_id?: int|null, date_from: string, date_to: string}  $filters
     */
    public function buildWorkbook(array $filters): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Accessi utente');

        $headings = [
            'Log ID',
            'User ID',
            'Nome',
            'Cognome',
            'Email',
            'IP address',
            'User agent',
            'Login effettuato il',
            'Creato il',
            'Aggiornato il',
        ];

        $rows = $this->rows($filters);

        $this->fillSheet($sheet, $headings, $rows);

        return $spreadsheet;
    }

    public function writeToOutput(Spreadsheet $spreadsheet): void
    {
        (new Xlsx($spreadsheet))->save('php://output');
    }

    public function store(UserAccessExport $userAccessExport): string
    {
        $fileStem = sprintf(
            'user-access-export-%d-%s',
            $userAccessExport->getKey(),
            now()->format('YmdHis')
        );

        $outputPath = sprintf(
            'user-access-exports/%d/%s.xlsx',
            $userAccessExport->getKey(),
            Str::slug($fileStem, '-')
        );

        $contents = $this->buildWorkbookContents([
            'scope_type' => $userAccessExport->scope_type,
            'job_dimension' => $userAccessExport->job_dimension,
            'job_dimension_id' => $userAccessExport->job_dimension_id,
            'date_from' => $userAccessExport->date_from->toDateString(),
            'date_to' => $userAccessExport->date_to->toDateString(),
        ]);

        $stored = Storage::disk($userAccessExport->output_disk)->put($outputPath, $contents);

        if (! $stored) {
            throw new RuntimeException('Unable to store user access export.');
        }

        return $outputPath;
    }

    /**
     * @param  array{scope_type: string, user_id?: int|null, job_dimension?: string|null, job_dimension_id?: int|null, date_from: string, date_to: string}  $filters
     */
    public function downloadFileName(array $filters): string
    {
        $scope = $filters['scope_type'] === ExportUserAccessRequest::SCOPE_USER
            ? 'utente'
            : Str::slug((string) (VideoReportRequest::jobDimensionOptions()[$filters['job_dimension'] ?? '']['label'] ?? 'gruppo'));

        return sprintf(
            'accessi-utenti-%s-%s-%s.xlsx',
            $scope,
            Carbon::parse($filters['date_from'])->format('Ymd'),
            Carbon::parse($filters['date_to'])->format('Ymd'),
        );
    }

    /**
     * @param  array{scope_type: string, user_id?: int|null, job_dimension?: string|null, job_dimension_id?: int|null, date_from: string, date_to: string}  $filters
     */
    public function buildWorkbookContents(array $filters): string
    {
        $spreadsheet = $this->buildWorkbook($filters);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'user-access-export-');

        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException('Unable to create temporary user access export file.');
        }

        try {
            (new Xlsx($spreadsheet))->save($temporaryFile);
            $contents = file_get_contents($temporaryFile);

            if ($contents === false) {
                throw new RuntimeException('Unable to read temporary user access export file.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    /**
     * @param  array{scope_type: string, user_id?: int|null, job_dimension?: string|null, job_dimension_id?: int|null, date_from: string, date_to: string}  $filters
     * @return array<int, array<int, string|null>>
     */
    private function rows(array $filters): array
    {
        $query = DB::table('users_access_log')
            ->join('users', 'users.id', '=', 'users_access_log.user_id')
            ->select([
                'users_access_log.id as log_id',
                'users_access_log.user_id',
                'users.name',
                'users.surname',
                'users.email',
                'users_access_log.ip_address',
                'users_access_log.user_agent',
                'users_access_log.logged_in_at',
                'users_access_log.created_at',
                'users_access_log.updated_at',
            ])
            ->whereBetween('users_access_log.logged_in_at', [
                Carbon::parse($filters['date_from'])->startOfDay(),
                Carbon::parse($filters['date_to'])->endOfDay(),
            ])
            ->orderBy('users_access_log.logged_in_at')
            ->orderBy('users_access_log.id');

        if ($filters['scope_type'] === ExportUserAccessRequest::SCOPE_USER) {
            $query->where('users.id', $filters['user_id']);
        } else {
            $dimension = VideoReportRequest::jobDimensionOptions()[$filters['job_dimension']];
            $query->where('users.'.$dimension['user_column'], $filters['job_dimension_id']);
        }

        return $query
            ->get()
            ->map(fn (object $row): array => [
                (string) $row->log_id,
                (string) $row->user_id,
                $row->name,
                $row->surname,
                $row->email,
                $row->ip_address,
                $row->user_agent,
                $this->formatDateTime($row->logged_in_at),
                $this->formatDateTime($row->created_at),
                $this->formatDateTime($row->updated_at),
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string|null>>  $rows
     */
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

        $lastColumn = Coordinate::stringFromColumnIndex(count($headings));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);

        for ($column = 1; $column <= count($headings); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))
                ->setWidth($this->columnWidth($headings, $rows, $column - 1));
        }
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, string|null>>  $rows
     */
    private function columnWidth(array $headings, array $rows, int $index): int
    {
        $maxLength = mb_strlen((string) ($headings[$index] ?? ''));

        foreach ($rows as $row) {
            $maxLength = max($maxLength, mb_strlen((string) ($row[$index] ?? '')));
        }

        return min(max($maxLength + 2, 14), 45);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->format('d/m/Y H:i:s');
        }

        return null;
    }
}
