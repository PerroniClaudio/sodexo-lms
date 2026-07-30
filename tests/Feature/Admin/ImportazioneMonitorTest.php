<?php

use App\Models\Importazione;
use Illuminate\Support\Facades\Storage;

it('shows all available import types in type filter', function () {
    actingAsRole('superadmin');

    $response = $this->get(route('admin.importazioni-monitor.index'));

    $response->assertOk();

    foreach (Importazione::availableTypes() as $type) {
        $response->assertSee('value="'.$type.'"', false);
        $response->assertSeeText(Importazione::typeLabelFor($type));
    }
});

it('shows the import recap and downloads its source file', function () {
    $user = actingAsRole('superadmin');
    Storage::fake();
    Storage::put('imports/users/import.xlsx', 'test file');

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USERS,
        'created_by' => $user->id,
        'status' => Importazione::STATUS_FINISHED,
        'file_path' => 'imports/users/import.xlsx',
        'original_file_name' => 'import.xlsx',
        'summary' => [
            'processed_records' => 2,
            'created_users' => 2,
            'risk_low' => 1,
            'risk_medium' => 1,
            'risk_high' => 0,
        ],
    ]);

    $this->get(route('admin.importazioni-monitor.index'))
        ->assertOk()
        ->assertSee('data-tip="Informazioni e log importazione"', false)
        ->assertDontSee('<th>File</th>', false)
        ->assertSeeText('Utenze create')
        ->assertSeeText('Rischio basso');

    $this->get(route('admin.imports.download', $importazione))
        ->assertDownload('import.xlsx');
});
