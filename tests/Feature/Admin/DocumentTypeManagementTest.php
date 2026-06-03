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
