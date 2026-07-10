<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshAllJobBasedRequirementsJob;
use App\Models\RequirementCalculationRun;
use App\Models\User;
use App\Services\JobBasedRequirementEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobBasedRequirementStatusController extends Controller
{
    public function __construct(
        private readonly JobBasedRequirementEngineService $jobBasedRequirementEngineService,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'data' => $this->jobBasedRequirementEngineService->globalStatus(),
        ]);
    }

    public function refreshAll(Request $request): JsonResponse
    {
        RefreshAllJobBasedRequirementsJob::dispatch((int) $request->user()->getAuthIdentifier());

        return response()->json([
            'success' => true,
            'message' => __('Ricalcolo totale accodato con successo.'),
            'data' => $this->jobBasedRequirementEngineService->globalStatus(),
        ], 202);
    }

    public function userSummary(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->jobBasedRequirementEngineService->cachedSummaryForUser($user->fresh(['jobBasedRequirements'])),
        ]);
    }

    public function refreshUser(Request $request, User $user): JsonResponse
    {
        $run = RequirementCalculationRun::query()->create([
            'scope' => 'user',
            'status' => 'running',
            'triggered_by_user_id' => (int) $request->user()->getAuthIdentifier(),
            'target_user_id' => (int) $user->getKey(),
            'started_at' => now(),
        ]);

        try {
            $this->jobBasedRequirementEngineService->recalculateUser($user);

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Requisiti ruolo/mansione ricalcolati con successo.'),
                'data' => $this->jobBasedRequirementEngineService->cachedSummaryForUser($user->fresh(['jobBasedRequirements'])),
            ]);
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'meta' => ['error' => $exception->getMessage()],
            ]);

            throw $exception;
        }
    }
}
