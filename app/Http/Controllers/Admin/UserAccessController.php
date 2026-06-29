<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportUserAccessRequest;
use App\Jobs\GenerateUserAccessExport;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\User;
use App\Models\UserAccessExport;
use App\Models\VideoReportRequest;
use App\Services\UserAccessExporter;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAccessController extends Controller
{
    public function index(): View
    {
        return view('admin.user-accesses.index', [
            'userAccessExports' => UserAccessExport::query()
                ->select([
                    'id',
                    'requested_by',
                    'status',
                    'scope_type',
                    'job_dimension',
                    'job_dimension_id',
                    'date_from',
                    'date_to',
                    'error_message',
                ])
                ->with('requester:id,name,surname')
                ->latest()
                ->paginate(20),
            'users' => User::query()
                ->orderBy('surname')
                ->orderBy('name')
                ->get(['id', 'name', 'surname', 'email']),
            'jobDimensionOptions' => VideoReportRequest::jobDimensionOptions(),
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

    public function export(ExportUserAccessRequest $request, UserAccessExporter $exporter, ResponseFactory $responseFactory): StreamedResponse|RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['scope_type'] === ExportUserAccessRequest::SCOPE_JOB_DIMENSION) {
            $userAccessExport = UserAccessExport::query()->create([
                'requested_by' => Auth::id(),
                'scope_type' => $validated['scope_type'],
                'job_dimension' => $validated['job_dimension'],
                'job_dimension_id' => (int) $validated['job_dimension_id'],
                'date_from' => $validated['date_from'],
                'date_to' => $validated['date_to'],
                'status' => UserAccessExport::STATUS_PENDING,
                'output_disk' => Storage::getDefaultDriver(),
            ]);

            GenerateUserAccessExport::dispatch($userAccessExport);

            return redirect()
                ->route('admin.user-accesses.index')
                ->with('status', __('Richiesta export accodata con successo.'));
        }

        $fileName = $exporter->downloadFileName($validated);

        return $responseFactory->streamDownload(function () use ($exporter, $validated): void {
            $spreadsheet = $exporter->buildWorkbook($validated);

            try {
                $exporter->writeToOutput($spreadsheet);
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function show(UserAccessExport $userAccessExport): JsonResponse
    {
        return response()->json([
            'id' => $userAccessExport->getKey(),
            'status' => $userAccessExport->status,
            'status_label' => $userAccessExport->statusLabel(),
            'status_badge_class' => $userAccessExport->statusBadgeClass(),
            'is_terminal' => $userAccessExport->isTerminal(),
            'error_message' => $userAccessExport->error_message,
            'download_url' => $userAccessExport->status === UserAccessExport::STATUS_COMPLETED
                ? route('admin.user-accesses.download', $userAccessExport)
                : null,
        ]);
    }

    public function download(UserAccessExport $userAccessExport): StreamedResponse
    {
        abort_unless($userAccessExport->status === UserAccessExport::STATUS_COMPLETED, 404);
        abort_unless($userAccessExport->hasGeneratedFile(), 404);

        $disk = Storage::disk($userAccessExport->output_disk);

        abort_unless($disk->exists($userAccessExport->output_path), 404);

        return $disk->download(
            $userAccessExport->output_path,
            $userAccessExport->outputFileName()
        );
    }
}
