<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVideoReportRequest;
use App\Jobs\GenerateVideoReport;
use App\Models\Course;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\VideoReportRequest;
use App\Support\CloudStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VideoReportController extends Controller
{
    public function index(): View
    {
        return view('admin.video-reports.index', [
            'videoReportRequests' => VideoReportRequest::query()
                ->select([
                    'id',
                    'requested_by',
                    'status',
                    'scope_type',
                    'report_type',
                    'course_id',
                    'job_dimension',
                    'job_dimension_id',
                    'date_from',
                    'date_to',
                    'error_message',
                ])
                ->with([
                    'course:id,title',
                    'requester:id,name,surname',
                ])
                ->latest()
                ->paginate(20),
            'courses' => Course::query()
                ->exportableForAuditTrail()
                ->orderBy('title')
                ->get(['id', 'title']),
            'jobDimensionOptions' => VideoReportRequest::jobDimensionOptions(),
            'reportTypeOptions' => VideoReportRequest::reportTypeOptions(),
            'jobDimensionValues' => [
                VideoReportRequest::JOB_DIMENSION_SECTOR => JobSector::query()->orderBy('name')->get(['id', 'name']),
                VideoReportRequest::JOB_DIMENSION_CATEGORY => JobCategory::query()->orderBy('name')->get(['id', 'name']),
                VideoReportRequest::JOB_DIMENSION_LEVEL => JobLevel::query()->orderBy('name')->get(['id', 'name']),
                VideoReportRequest::JOB_DIMENSION_TASK => JobTask::query()->orderBy('name')->get(['id', 'name']),
                VideoReportRequest::JOB_DIMENSION_ROLE => JobRole::query()->orderBy('name')->get(['id', 'name']),
                VideoReportRequest::JOB_DIMENSION_UNIT => JobUnit::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(StoreVideoReportRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $videoReportRequest = VideoReportRequest::query()->create([
            'requested_by' => Auth::id(),
            'status' => VideoReportRequest::STATUS_PENDING,
            'scope_type' => $validated['scope_type'],
            'report_type' => $validated['report_type'],
            'course_id' => $validated['scope_type'] === VideoReportRequest::SCOPE_COURSE
                ? (int) $validated['course_id']
                : null,
            'job_dimension' => $validated['scope_type'] === VideoReportRequest::SCOPE_JOB_DIMENSION
                ? $validated['job_dimension']
                : null,
            'job_dimension_id' => $validated['scope_type'] === VideoReportRequest::SCOPE_JOB_DIMENSION
                ? (int) $validated['job_dimension_id']
                : null,
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'output_disk' => CloudStorage::disk(),
        ]);

        GenerateVideoReport::dispatch($videoReportRequest);

        return redirect()
            ->route('admin.video-reports.index')
            ->with('status', __('Richiesta report accodata con successo.'));
    }

    public function show(VideoReportRequest $videoReportRequest): JsonResponse
    {
        return response()->json([
            'id' => $videoReportRequest->getKey(),
            'status' => $videoReportRequest->status,
            'status_label' => $videoReportRequest->statusLabel(),
            'status_badge_class' => $videoReportRequest->statusBadgeClass(),
            'is_terminal' => $videoReportRequest->isTerminal(),
            'error_message' => $videoReportRequest->error_message,
            'download_url' => $videoReportRequest->status === VideoReportRequest::STATUS_COMPLETED
                ? route('admin.video-reports.download', $videoReportRequest)
                : null,
        ]);
    }

    public function download(VideoReportRequest $videoReportRequest): StreamedResponse
    {
        abort_unless($videoReportRequest->status === VideoReportRequest::STATUS_COMPLETED, 404);
        abort_unless($videoReportRequest->hasGeneratedFile(), 404);

        $disk = Storage::disk($videoReportRequest->output_disk);

        abort_unless($disk->exists($videoReportRequest->output_path), 404);

        return $disk->download(
            $videoReportRequest->output_path,
            $videoReportRequest->outputFileName()
        );
    }
}
