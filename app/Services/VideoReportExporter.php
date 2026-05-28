<?php

namespace App\Services;

use App\Models\Course;
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

class VideoReportExporter
{
    private const PROGRESS_COLUMNS = [
        'course_id',
        'course_title',
        'module_id',
        'module_title',
        'video_id',
        'video_title',
        'user_id',
        'user_name',
        'user_surname',
        'user_email',
        'user_fiscal_code',
        'job_sector',
        'job_category',
        'job_level',
        'job_task',
        'job_role',
        'job_unit',
        'module_status',
        'time_spent_seconds',
        'video_current_second',
        'video_max_second',
        'started_at',
        'last_accessed_at',
        'completed_at',
    ];

    private const AUDIT_COLUMNS = [
        'course_id',
        'course_title',
        'module_id',
        'module_title',
        'video_id',
        'video_title',
        'user_id',
        'user_name',
        'user_surname',
        'user_email',
        'user_fiscal_code',
        'session_uuid',
        'event_uuid',
        'event_type',
        'position_second',
        'max_second_client',
        'delta_watched_seconds',
        'from_second',
        'to_second',
        'player_ended',
        'was_blocked',
        'occurred_at',
    ];

    public function buildWorkbook(VideoReportRequest $videoReportRequest): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;

        $progressSheet = $spreadsheet->getActiveSheet();
        $progressSheet->setTitle('Avanzamenti');
        $this->fillSheet(
            $progressSheet,
            $this->translatedHeadings('progress', self::PROGRESS_COLUMNS),
            $this->progressRows($videoReportRequest)
        );

        $auditSheet = $spreadsheet->createSheet();
        $auditSheet->setTitle('Audit Trail');
        $this->fillSheet(
            $auditSheet,
            $this->translatedHeadings('audit', self::AUDIT_COLUMNS),
            $this->auditRows($videoReportRequest)
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function store(VideoReportRequest $videoReportRequest): string
    {
        $fileStem = sprintf(
            'video-report-%d-%s',
            $videoReportRequest->getKey(),
            now()->format('YmdHis')
        );

        $outputPath = sprintf(
            'video-reports/%d/%s.xlsx',
            $videoReportRequest->getKey(),
            Str::slug($fileStem, '-')
        );

        $contents = $this->buildWorkbookContents($videoReportRequest);

        $stored = Storage::disk($videoReportRequest->output_disk)->put($outputPath, $contents);

        if (! $stored) {
            throw new RuntimeException('Unable to store video report export.');
        }

        return $outputPath;
    }

    public function buildWorkbookContents(VideoReportRequest $videoReportRequest): string
    {
        $spreadsheet = $this->buildWorkbook($videoReportRequest);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'video-report-');

        if ($temporaryFile === false) {
            $spreadsheet->disconnectWorksheets();

            throw new RuntimeException('Unable to create temporary video report export file.');
        }

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($temporaryFile);

            $contents = file_get_contents($temporaryFile);

            if ($contents === false) {
                throw new RuntimeException('Unable to read temporary video report export file.');
            }

            return $contents;
        } finally {
            $spreadsheet->disconnectWorksheets();

            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }
    }

    public function progressRows(VideoReportRequest $videoReportRequest): array
    {
        return $this->baseQuery($videoReportRequest)
            ->select([
                'courses.id as course_id',
                'courses.title as course_title',
                'modules.id as module_id',
                'modules.title as module_title',
                'modules.order as module_order',
                'videos.id as video_id',
                'videos.title as video_title',
                'users.id as user_id',
                'users.name as user_name',
                'users.surname as user_surname',
                'users.email as user_email',
                'users.fiscal_code as user_fiscal_code',
                'job_sectors.name as job_sector',
                'job_categories.name as job_category',
                'job_levels.name as job_level',
                'job_tasks.name as job_task',
                'job_roles.name as job_role',
                'job_units.name as job_unit',
                'module_progress.status as module_status',
                'module_progress.time_spent_seconds',
                'module_progress.video_current_second',
                'module_progress.video_max_second',
                'module_progress.started_at',
                'module_progress.last_accessed_at',
                'module_progress.completed_at',
            ])
            ->distinct()
            ->orderBy('courses.title')
            ->orderBy('module_order')
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->get()
            ->map(fn (object $row): array => [
                $row->course_id,
                $row->course_title,
                $row->module_id,
                $row->module_title,
                $row->video_id,
                $row->video_title,
                $row->user_id,
                $row->user_name,
                $row->user_surname,
                $row->user_email,
                $row->user_fiscal_code,
                $row->job_sector,
                $row->job_category,
                $row->job_level,
                $row->job_task,
                $row->job_role,
                $row->job_unit,
                $row->module_status,
                $row->time_spent_seconds,
                $row->video_current_second,
                $row->video_max_second,
                $this->formatDateTime($row->started_at),
                $this->formatDateTime($row->last_accessed_at),
                $this->formatDateTime($row->completed_at),
            ])
            ->all();
    }

    public function auditRows(VideoReportRequest $videoReportRequest): array
    {
        return $this->baseQuery($videoReportRequest)
            ->select([
                'courses.id as course_id',
                'courses.title as course_title',
                'modules.id as module_id',
                'modules.title as module_title',
                'videos.id as video_id',
                'videos.title as video_title',
                'users.id as user_id',
                'users.name as user_name',
                'users.surname as user_surname',
                'users.email as user_email',
                'users.fiscal_code as user_fiscal_code',
                'video_tracking_events.session_uuid',
                'video_tracking_events.event_uuid',
                'video_tracking_events.event_type',
                'video_tracking_events.position_second',
                'video_tracking_events.max_second_client',
                'video_tracking_events.delta_watched_seconds',
                'video_tracking_events.from_second',
                'video_tracking_events.to_second',
                'video_tracking_events.player_ended',
                'video_tracking_events.was_blocked',
                'video_tracking_events.occurred_at',
            ])
            ->orderBy('courses.title')
            ->orderBy('modules.order')
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->orderBy('video_tracking_events.occurred_at')
            ->get()
            ->map(fn (object $row): array => [
                $row->course_id,
                $row->course_title,
                $row->module_id,
                $row->module_title,
                $row->video_id,
                $row->video_title,
                $row->user_id,
                $row->user_name,
                $row->user_surname,
                $row->user_email,
                $row->user_fiscal_code,
                $row->session_uuid,
                $row->event_uuid,
                $row->event_type,
                $row->position_second,
                $row->max_second_client,
                $row->delta_watched_seconds,
                $row->from_second,
                $row->to_second,
                (int) $row->player_ended,
                (int) $row->was_blocked,
                $this->formatDateTime($row->occurred_at),
            ])
            ->all();
    }

    private function baseQuery(VideoReportRequest $videoReportRequest): Builder
    {
        $dateFrom = $videoReportRequest->date_from?->startOfDay() ?? now()->startOfDay();
        $dateTo = $videoReportRequest->date_to?->endOfDay() ?? now()->endOfDay();

        $query = DB::table('video_tracking_events')
            ->join('module_user as module_progress', 'module_progress.id', '=', 'video_tracking_events.module_progress_id')
            ->join('course_user', 'course_user.id', '=', 'video_tracking_events.course_user_id')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->join('modules', 'modules.id', '=', 'video_tracking_events.module_id')
            ->join('videos', 'videos.id', '=', 'video_tracking_events.video_id')
            ->join('users', 'users.id', '=', 'video_tracking_events.user_id')
            ->leftJoin('job_sectors', 'job_sectors.id', '=', 'users.job_sector_id')
            ->leftJoin('job_categories', 'job_categories.id', '=', 'users.job_category_id')
            ->leftJoin('job_levels', 'job_levels.id', '=', 'users.job_level_id')
            ->leftJoin('job_tasks', 'job_tasks.id', '=', 'users.job_task_id')
            ->leftJoin('job_roles', 'job_roles.id', '=', 'users.job_role_id')
            ->leftJoin('job_units', 'job_units.id', '=', 'users.job_unit_id')
            ->whereIn('courses.type', Course::AUDIT_TRAIL_TYPES)
            ->where('modules.type', Module::TYPE_VIDEO)
            ->whereBetween('video_tracking_events.occurred_at', [$dateFrom, $dateTo]);

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

    private function translatedHeadings(string $section, array $columns): array
    {
        return array_map(
            fn (string $column): string => __('video-report.columns.'.$section.'.'.$column),
            $columns,
        );
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

        return min(max($maxLength + 2, 10), 60);
    }
}
