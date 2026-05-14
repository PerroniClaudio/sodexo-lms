<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentConversionJobStatus;
use App\Http\Controllers\Controller;
use App\Models\DocumentConversionJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentConversionJobDebugController extends Controller
{
    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'status', 'attempts', 'created_at', 'updated_at', 'completed_at'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'created_at';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $allowedStatuses = collect(DocumentConversionJobStatus::cases())
            ->map(fn (DocumentConversionJobStatus $jobStatus): string => $jobStatus->value)
            ->all();

        $query = DocumentConversionJob::query()
            ->when($status !== '' && in_array($status, $allowedStatuses, true), function ($builder) use ($status) {
                $builder->where('status', $status);
            })
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($builder) use ($search) {
                    $builder
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('input_path', 'like', "%{$search}%")
                        ->orWhere('output_path', 'like', "%{$search}%")
                        ->orWhere('worker_id', 'like', "%{$search}%")
                        ->orWhere('error_message', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction);

        $statusCounts = DocumentConversionJob::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.document-conversion-jobs.index', [
            'jobs' => $query->paginate(20)->withQueryString(),
            'statuses' => DocumentConversionJobStatus::cases(),
            'statusCounts' => collect(DocumentConversionJobStatus::cases())
                ->mapWithKeys(fn (DocumentConversionJobStatus $jobStatus): array => [
                    $jobStatus->value => (int) ($statusCounts[$jobStatus->value] ?? 0),
                ]),
            'selectedStatus' => $status,
            'tableDirection' => $direction,
            'tableSearch' => $search,
            'tableSort' => $sort,
        ]);
    }

    public function retry(DocumentConversionJob $documentConversionJob): RedirectResponse
    {
        if (! $documentConversionJob->canBeRetried()) {
            return redirect()
                ->route('admin.document-conversion-jobs.index')
                ->with('error', __('Questo job non può essere ripetuto.'));
        }

        if (! Storage::disk($documentConversionJob->input_disk)->exists($documentConversionJob->input_path)) {
            return redirect()
                ->route('admin.document-conversion-jobs.index')
                ->with('error', __('Il file sorgente non è più disponibile.'));
        }

        DocumentConversionJob::query()->create([
            'status' => DocumentConversionJobStatus::PENDING,
            'input_disk' => $documentConversionJob->input_disk,
            'input_path' => $documentConversionJob->input_path,
            'output_disk' => $documentConversionJob->output_disk,
            'output_path' => $documentConversionJob->output_path,
            'attempts' => 0,
            'max_attempts' => $documentConversionJob->max_attempts,
            'locked_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'worker_id' => null,
        ]);

        return redirect()
            ->route('admin.document-conversion-jobs.index')
            ->with('status', __('Job di conversione accodato nuovamente.'));
    }

    public function download(DocumentConversionJob $documentConversionJob): StreamedResponse
    {
        abort_unless($documentConversionJob->hasGeneratedFile(), 404);

        $disk = Storage::disk($documentConversionJob->output_disk);

        abort_unless($disk->exists($documentConversionJob->output_path), 404);

        return $disk->download(
            $documentConversionJob->output_path,
            $documentConversionJob->outputFileName()
        );
    }
}
