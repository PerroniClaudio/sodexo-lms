<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Models\Course;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PartnerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());

        return view('admin.partners.index', [
            'partners' => Partner::query()
                ->withCount('courses')
                ->when($search !== '', fn ($query) => $query->where('ragione_sociale', 'like', "%{$search}%"))
                ->orderBy('ragione_sociale')
                ->paginate(10)
                ->withQueryString(),
            'tableSearch' => $search,
        ]);
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        Partner::query()->create($request->validated());

        return redirect()
            ->route('admin.partners.index')
            ->with('status', __('Partner creato con successo.'));
    }

    public function edit(Partner $partner): View
    {
        $partner->load('courses');

        return view('admin.partners.edit', [
            'partner' => $partner,
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $partner->update($request->validated());

        return redirect()
            ->route('admin.partners.edit', $partner)
            ->with('status', __('Partner aggiornato con successo.'));
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        if ($partner->courses()->exists()) {
            return redirect()
                ->route('admin.partners.index')
                ->with('error', __('Non puoi eliminare un partner associato a uno o più corsi.'));
        }

        $partner->delete();

        return redirect()
            ->route('admin.partners.index')
            ->with('status', __('Partner eliminato con successo.'));
    }

    public function detachCourse(Partner $partner, Course $course): RedirectResponse
    {
        $partner->courses()->detach($course);

        return redirect()
            ->route('admin.partners.edit', $partner)
            ->with('status', __('Associazione corso rimossa.'));
    }
}
