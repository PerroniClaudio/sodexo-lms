<?php

use App\Models\LanguageLevel;

beforeEach(function (): void {
    actingAsRole('admin');
});

it('reorders language levels and updates every order value', function () {
    $languageLevels = LanguageLevel::query()->ordered()->get();
    $reorderedIds = $languageLevels->pluck('id')->reverse()->values()->all();

    $response = $this->patchJson(route('admin.language-levels.reorder'), [
        'language_levels' => $reorderedIds,
    ]);

    $response->assertOk()->assertJson([
        'message' => 'Ordine livelli lingua aggiornato con successo.',
    ]);

    $languageLevels
        ->sortByDesc('sort_order')
        ->values()
        ->each(fn (LanguageLevel $languageLevel, int $index) => expect($languageLevel->fresh()->sort_order)->toBe($index + 1));
});

it('rejects invalid language level reorder payloads', function () {
    [$a1, $a2, $b1] = LanguageLevel::query()->ordered()->take(3)->get()->all();

    $response = $this->patchJson(route('admin.language-levels.reorder'), [
        'language_levels' => [$a1->id, $b1->id],
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['language_levels']);

    expect($a1->fresh()->sort_order)->toBe(1);
    expect($a2->fresh()->sort_order)->toBe(2);
    expect($b1->fresh()->sort_order)->toBe(3);
});

it('updates a language level without failing the unique name validation on the current record', function () {
    $languageLevel = LanguageLevel::query()->ordered()->firstOrFail();

    $response = $this->from(route('admin.language-levels.edit', $languageLevel))
        ->put(route('admin.language-levels.update', $languageLevel), [
            'name' => $languageLevel->name,
            'is_default' => $languageLevel->is_default,
        ]);

    $response->assertRedirect(route('admin.language-levels.edit', $languageLevel));
    $response->assertSessionHasNoErrors();
});
