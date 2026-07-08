<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyDivisionRequest;
use App\Models\CompanyDivision;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyDivisionController extends Controller
{
    public function index(): View
    {
        return view('admin.company-divisions.index', [
            'companyDivisions' => CompanyDivision::query()
                ->withCount(['users', 'admins', 'courses'])
                ->orderBy('name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.company-divisions.create', $this->formData(new CompanyDivision));
    }

    public function store(StoreCompanyDivisionRequest $request): RedirectResponse
    {
        $companyDivision = CompanyDivision::query()->create($this->divisionData($request));
        $this->syncRelations($companyDivision, $request);

        return redirect()
            ->route('admin.company-divisions.edit', $companyDivision)
            ->with('success', __('Divisione aziendale creata con successo.'));
    }

    public function edit(CompanyDivision $companyDivision): View
    {
        return view('admin.company-divisions.edit', $this->formData($companyDivision));
    }

    public function update(StoreCompanyDivisionRequest $request, CompanyDivision $companyDivision): RedirectResponse
    {
        $companyDivision->update($this->divisionData($request, $companyDivision));
        $this->syncRelations($companyDivision, $request);

        return redirect()
            ->route('admin.company-divisions.edit', $companyDivision)
            ->with('success', __('Divisione aziendale aggiornata con successo.'));
    }

    public function destroy(CompanyDivision $companyDivision): RedirectResponse
    {
        if ($companyDivision->logo_path !== null) {
            Storage::disk('public')->delete($companyDivision->logo_path);
        }

        $companyDivision->delete();

        return redirect()
            ->route('admin.company-divisions.index')
            ->with('success', __('Divisione aziendale eliminata con successo.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(CompanyDivision $companyDivision): array
    {
        return [
            'companyDivision' => $companyDivision,
            'admins' => User::role('admin')
                ->orderBy('surname')
                ->orderBy('name')
                ->get(['id', 'name', 'surname', 'email']),
            'users' => User::role('user')
                ->orderBy('surname')
                ->orderBy('name')
                ->get(['id', 'name', 'surname', 'email', 'company_division_id']),
            'courses' => Course::query()
                ->orderBy('title')
                ->get(['id', 'title', 'code']),
        ];
    }

    /**
     * @return array{name: string, vat_number: ?string, logo_path?: string}
     */
    private function divisionData(StoreCompanyDivisionRequest $request, ?CompanyDivision $companyDivision = null): array
    {
        $validated = $request->validated();
        $data = [
            'name' => $validated['name'],
            'vat_number' => $validated['vat_number'] ?? null,
        ];

        if ($request->hasFile('logo')) {
            if ($companyDivision?->logo_path !== null) {
                Storage::disk('public')->delete($companyDivision->logo_path);
            }

            $data['logo_path'] = $request->file('logo')->store('company-divisions', 'public');
        }

        return $data;
    }

    private function syncRelations(CompanyDivision $companyDivision, StoreCompanyDivisionRequest $request): void
    {
        if ($request->boolean('sync_users') || $request->isMethod('POST')) {
            $userIds = $request->validated('user_ids', []);

            $companyDivision->users()
                ->whereNotIn('id', $userIds)
                ->update(['company_division_id' => null]);

            User::query()
                ->whereKey($userIds)
                ->update(['company_division_id' => $companyDivision->getKey()]);
        }

        if ($request->boolean('sync_admins') || $request->isMethod('POST')) {
            $companyDivision->admins()->sync($request->validated('admin_ids', []));
        }

        if ($request->boolean('sync_courses') || $request->isMethod('POST')) {
            $companyDivision->courses()->sync($request->validated('course_ids', []));
        }
    }
}
