<?php

namespace App\Services;

use App\Models\Course;
use App\Models\LiveStreamAuditEvent;
use App\Models\Module;
use App\Models\VideoReportRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class LiveAuditTrailExporter
{
    private const AUDIT_COLUMNS = [
        'course_id',
        'course_title',
        'module_id',
        'module_title',
        'live_stream_session_id',
        'live_stream_participant_id',
        'live_stream_hand_raise_id',
        'user_id',
        'user_name',
        'user_surname',
        'user_email',
        'user_fiscal_code',
        'app_role',
        'event_type',
        'occurred_at',
        'context',
    ];

    public function buildWorkbook(VideoReportRequest $videoReportRequest): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $auditSheet = $spreadsheet->getActiveSheet();
        $auditSheet->setTitle('Audit Trail');
        $this->fillSheet(
            $auditSheet,
            $this->headings(),
            $this->auditRows($videoReportRequest),
        );

        return $spreadsheet;
    }

    public function store(VideoReportRequest $videoReportRequest): string
    {
        $fileStem = sprintf(
            'live-audit-trail-%d-%s',
            $videoReportRequest->getKey(),
            now()->format('YmdHis')
        );

        $outputPath = sprintf(
            'live-audit-trails/%d/%s.xlsx',
            $videoReportRequest->getKey(),
            Str::slug($fileStem, '-')
        );

        $contents = $this->buildWorkbookContents($videoReportRequest);

        $stored = Storage::disk($videoReportRequest->output_disk)->put($outputPath, $contents);

        if (! $stored) {
            throw new RuntimeException('Unable to store live audit trail export.');
        }

        return $outputPath;
    }

    public function buildWorkbookContents(VideoReportRequest $videoReportRequest): string
    {
        $spreadsheet = $this->buildWorkbook($videoReportRequest);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'live-audit-trail-');

        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException('Unable to create temporary live audit trail export file.');
        }

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($temporaryFile);

            $contents = file_get_contents($temporaryFile);

            if ($contents === false) {
                throw new RuntimeException('Unable to read temporary live audit trail export file.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    public function auditRows(VideoReportRequest $videoReportRequest): array
    {
        return $this->baseQuery($videoReportRequest)
            ->select([
                'courses.id as course_id',
                'courses.title as course_title',
                'modules.id as module_id',
                'modules.title as module_title',
                'live_stream_audit_events.live_stream_session_id',
                'live_stream_audit_events.live_stream_participant_id',
                'live_stream_audit_events.live_stream_hand_raise_id',
                'users.id as user_id',
                'users.name as user_name',
                'users.surname as user_surname',
                'users.email as user_email',
                'users.fiscal_code as user_fiscal_code',
                'live_stream_audit_events.app_role',
                'live_stream_audit_events.event_type',
                'live_stream_audit_events.occurred_at',
                'live_stream_audit_events.context',
            ])
            ->orderBy('courses.title')
            ->orderBy('modules.order')
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->orderBy('live_stream_audit_events.occurred_at')
            ->get()
            ->map(fn (object $row): array => [
                $row->course_id,
                $row->course_title,
                $row->module_id,
                $row->module_title,
                $row->live_stream_session_id,
                $row->live_stream_participant_id,
                $row->live_stream_hand_raise_id,
                $row->user_id,
                $row->user_name,
                $row->user_surname,
                $row->user_email,
                $row->user_fiscal_code,
                $row->app_role,
                $row->event_type,
                $this->formatDateTime($row->occurred_at),
                is_array($row->context ?? null) ? json_encode($row->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $row->context,
            ])
            ->all();
    }

    private function baseQuery(VideoReportRequest $videoReportRequest): Builder
    {
        $dateFrom = $videoReportRequest->date_from?->startOfDay() ?? now()->startOfDay();
        $dateTo = $videoReportRequest->date_to?->endOfDay() ?? now()->endOfDay();

        $query = DB::table('live_stream_audit_events')
            ->join('live_stream_sessions', 'live_stream_sessions.id', '=', 'live_stream_audit_events.live_stream_session_id')
            ->join('modules', 'modules.id', '=', 'live_stream_audit_events.module_id')
            ->join('courses', 'courses.id', '=', 'modules.belongsTo')
            ->join('users', 'users.id', '=', 'live_stream_audit_events.user_id')
            ->whereIn('courses.type', Course::AUDIT_TRAIL_TYPES)
            ->where('modules.type', Module::TYPE_LIVE)
            ->whereIn('live_stream_audit_events.event_type', [
                LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED,
                LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED,
                LiveStreamAuditEvent::TYPE_HAND_RAISE_REQUESTED,
            ])
            ->whereBetween('live_stream_audit_events.occurred_at', [$dateFrom, $dateTo]);

        if ($videoReportRequest->scope_type === VideoReportRequest::SCOPE_COURSE && $videoReportRequest->course_id !== null) {
            $query->where('courses.id', $videoReportRequest->course_id);
        }

        if (
            $videoReportRequest->scope_type === VideoReportRequest::SCOPE_JOB_DIMENSION
            && $videoReportRequest->job_dimension !== null
            && $videoReportRequest->job_dimension_id !== null
        ) {
            $dimension = VideoReportRequest::jobDimensionOptions()[$videoReportRequest->job_dimension] ?? null;

            if ($dimension !== null) {
                $query->where('users.'.$dimension['user_column'], $videoReportRequest->job_dimension_id);
            }
        }

        return $query;
    }

    private function headings(): array
    {
        return [
            __('ID corso'),
            __('Titolo corso'),
            __('ID modulo'),
            __('Titolo modulo'),
            __('ID sessione live'),
            __('ID partecipante'),
            __('ID alzata di mano'),
            __('ID utente'),
            __('Nome'),
            __('Cognome'),
            __('Email'),
            __('Codice fiscale'),
            __('Ruolo app'),
            __('Tipo evento'),
            __('Avvenuto il'),
            __('Contesto'),
        ];
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
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

        $lastColumn = count($headings);

        for ($column = 1; $column <= $lastColumn; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
    }
}
