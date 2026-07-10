<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrainingPathCourseApproval;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrainingPathApprovalLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());

        $approvals = TrainingPathCourseApproval::query()
            ->where('status', TrainingPathCourseApproval::STATUS_APPROVED)
            ->with([
                'user:id,name,surname,fiscal_code,email',
                'trainingPath:id,title,code',
                'course:id,title,code',
                'reviewer:id,name,surname,email',
                'importazione:id,original_file_name',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->whereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('fiscal_code', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('trainingPath', fn ($pathQuery) => $pathQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('course', fn ($courseQuery) => $courseQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('reviewer', fn ($reviewerQuery) => $reviewerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest('reviewed_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.tools.training-path-approvals', [
            'approvals' => $approvals,
            'tableSearch' => $search,
        ]);
    }
}
