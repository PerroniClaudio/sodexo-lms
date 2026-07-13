<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditEventIndexRequest;
use App\Http\Requests\StoreAuditExportRequest;
use App\Jobs\GenerateAuditExport;
use App\Models\AuditEvent;
use App\Models\AuditExport;
use App\Models\CompanyDivision;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditEventController extends Controller
{
    public function index(AuditEventIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.audit-events.index', [
            'auditEvents' => $this->query($filters)->select(['id', 'occurred_at', 'actor_user_id', 'actor_label', 'company_division_id', 'origin', 'action', 'subject_type', 'subject_id', 'subject_label'])->with(['actor:id,name,surname', 'companyDivision:id,name'])->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'actors' => User::query()->role(['admin', 'superadmin'])->orderBy('name')->get(['id', 'name', 'surname']),
            'companyDivisions' => CompanyDivision::query()->orderBy('name')->get(['id', 'name']),
            'actions' => AuditEvent::query()->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => AuditEvent::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'auditExports' => AuditExport::query()->where('requested_by', $request->user()->getKey())->latest()->limit(10)->get(),
        ]);
    }

    public function storeExport(StoreAuditExportRequest $request): RedirectResponse
    {
        $auditExport = AuditExport::query()->create(['requested_by' => $request->user()->getKey(), 'filters' => $request->validated()]);
        GenerateAuditExport::dispatch($auditExport)->afterCommit();

        return redirect()->route('admin.audit-events.index')->with('status', __('Export audit accodato con successo.'));
    }

    public function showExport(AuditExport $auditExport): JsonResponse
    {
        $this->authorizeExport($auditExport);

        return response()->json(['id' => $auditExport->getKey(), 'status' => $auditExport->status, 'error_message' => $auditExport->error_message, 'download_url' => $auditExport->hasGeneratedFile() ? route('admin.audit-events.exports.download', $auditExport) : null]);
    }

    public function downloadExport(AuditExport $auditExport): StreamedResponse
    {
        $this->authorizeExport($auditExport);
        abort_unless($auditExport->hasGeneratedFile(), 404);

        return Storage::disk($auditExport->output_disk)->download($auditExport->output_path, basename((string) $auditExport->output_path));
    }

    private function query(array $filters)
    {
        return AuditEvent::query()
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('occurred_at', '<=', $date))
            ->when($filters['actor_user_id'] ?? null, fn ($query, $id) => $query->where('actor_user_id', $id))
            ->when($filters['company_division_id'] ?? null, fn ($query, $id) => $query->where('company_division_id', $id))
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['subject_type'] ?? null, fn ($query, $type) => $query->where('subject_type', $type))
            ->when($filters['subject_id'] ?? null, fn ($query, $id) => $query->where('subject_id', $id));
    }

    private function authorizeExport(AuditExport $auditExport): void
    {
        abort_unless(request()->user()?->hasRole('superadmin') && $auditExport->requested_by === request()->user()?->getKey(), 403);
    }
}
