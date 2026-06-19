<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReorderLanguageLevelsRequest;
use App\Http\Requests\StoreLanguageLevelRequest;
use App\Http\Requests\UpdateLanguageLevelRequest;
use App\Models\LanguageLevel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LanguageLevelController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.language-level.index', [
            'languageLevels' => LanguageLevel::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.language-level.create');
    }

    public function store(StoreLanguageLevelRequest $request): RedirectResponse
    {
        $languageLevel = LanguageLevel::query()->create([
            ...$request->validated(),
            'sort_order' => ((int) LanguageLevel::query()->max('sort_order')) + 1,
        ]);

        return redirect()
            ->route('admin.language-levels.edit', $languageLevel)
            ->with('status', __('Livello lingua creato con successo.'));
    }

    public function show(LanguageLevel $languageLevel): never
    {
        abort(404);
    }

    public function edit(LanguageLevel $languageLevel): View
    {
        return view('admin.language-level.edit', compact('languageLevel'));
    }

    public function update(UpdateLanguageLevelRequest $request, LanguageLevel $languageLevel): RedirectResponse
    {
        $languageLevel->update($request->validated());

        return redirect()
            ->route('admin.language-levels.edit', $languageLevel)
            ->with('status', __('Livello lingua aggiornato con successo.'));
    }

    public function destroy(LanguageLevel $languageLevel): RedirectResponse
    {
        if ($languageLevel->requiredByCourses()->exists() || $languageLevel->grantedByCourses()->exists()) {
            return redirect()
                ->route('admin.language-levels.index')
                ->with('error', __('Non puoi eliminare un livello lingua gia utilizzato dai corsi.'));
        }

        if (User::query()->where('declared_language_level_id', $languageLevel->getKey())->exists()
            || User::query()->where('verified_language_level_id', $languageLevel->getKey())->exists()) {
            return redirect()
                ->route('admin.language-levels.index')
                ->with('error', __('Non puoi eliminare un livello lingua gia utilizzato dagli utenti.'));
        }

        $languageLevel->delete();

        return redirect()
            ->route('admin.language-levels.index')
            ->with('status', __('Livello lingua eliminato con successo.'));
    }

    public function updateDefault(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_language_level_id' => ['required', 'integer', 'exists:language_levels,id'],
        ]);

        $languageLevel = LanguageLevel::query()->findOrFail($validated['default_language_level_id']);
        $languageLevel->update(['is_default' => true]);

        return redirect()
            ->route('admin.language-levels.index')
            ->with('status', __('Livello lingua di default aggiornato con successo.'));
    }

    public function reorder(ReorderLanguageLevelsRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            $orderedLanguageLevelIds = collect($request->validated('language_levels'))->values();
            $temporaryOffset = ((int) LanguageLevel::query()->max('sort_order')) + $orderedLanguageLevelIds->count() + 1;

            $orderedLanguageLevelIds
                ->each(function (int $languageLevelId, int $index) use ($temporaryOffset): void {
                    LanguageLevel::query()
                        ->whereKey($languageLevelId)
                        ->update(['sort_order' => $temporaryOffset + $index]);
                });

            $orderedLanguageLevelIds
                ->each(function (int $languageLevelId, int $index): void {
                    LanguageLevel::query()
                        ->whereKey($languageLevelId)
                        ->update(['sort_order' => $index + 1]);
                });
        });

        return response()->json([
            'message' => __('Ordine livelli lingua aggiornato con successo.'),
        ]);
    }
}
