<?php

use App\Models\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('allows admins to create and update a document type', function () {
    $createResponse = $this->post(route('admin.document-types.store'), [
        'name' => 'Verbale firmato',
        'description' => 'Documento firmato dal docente',
    ]);

    $documentType = DocumentType::query()->firstOrFail();

    $createResponse->assertRedirect(route('admin.document-types.edit', $documentType));

    expect($documentType->name)->toBe('Verbale firmato');

    $updateResponse = $this->put(route('admin.document-types.update', $documentType), [
        'name' => 'Verbale finale',
        'description' => 'Documento finale firmato',
    ]);

    $updateResponse->assertRedirect(route('admin.document-types.edit', $documentType));

    expect($documentType->fresh()->name)->toBe('Verbale finale');
});

it('allows admins to delete and restore a document type', function () {
    $documentType = DocumentType::factory()->create();

    $deleteResponse = $this->delete(route('admin.document-types.destroy', $documentType));

    $deleteResponse->assertRedirect(route('admin.document-types.index'));
    expect(DocumentType::withTrashed()->findOrFail($documentType->getKey())->trashed())->toBeTrue();

    $restoreResponse = $this->post(route('admin.document-types.restore', $documentType->getKey()));

    $restoreResponse->assertRedirect(route('admin.document-types.index'));
    expect(DocumentType::query()->findOrFail($documentType->getKey())->trashed())->toBeFalse();
});

it('lists document types through the api keeping sorting and trashed filters available', function () {
    DocumentType::factory()->create([
        'name' => 'Verbale firmato',
        'description' => 'Documento firmato',
    ]);

    $deletedDocumentType = DocumentType::factory()->create([
        'name' => 'Scheda eliminata',
        'description' => 'Da mostrare solo con filtro trashed',
    ]);
    $deletedDocumentType->delete();

    $response = $this->getJson(route('admin.api.document-types.index', [
        'search' => 'Scheda',
        'sort' => 'name',
        'direction' => 'asc',
        'show_trashed' => 1,
    ]));

    $response->assertSuccessful()
        ->assertJsonPath('query.sort', 'name')
        ->assertJsonPath('query.direction', 'asc')
        ->assertJsonPath('query.show_trashed', true)
        ->assertJsonPath('data.0.name', 'Scheda eliminata')
        ->assertJsonPath('data.0.is_deleted', true);
});

it('allows admins to delete and restore a document type through the api', function () {
    $documentType = DocumentType::factory()->create();

    $deleteResponse = $this->deleteJson(route('admin.api.document-types.destroy', $documentType));

    $deleteResponse->assertOk()
        ->assertJsonPath('message', 'Tipologia documento eliminata con successo.');
    expect(DocumentType::withTrashed()->findOrFail($documentType->getKey())->trashed())->toBeTrue();

    $restoreResponse = $this->postJson(route('admin.api.document-types.restore', $documentType->getKey()));

    $restoreResponse->assertOk()
        ->assertJsonPath('message', 'Tipologia documento ripristinata con successo.');
    expect(DocumentType::query()->findOrFail($documentType->getKey())->trashed())->toBeFalse();
});
