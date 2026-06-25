<?php

use App\Models\Importazione;

it('shows all available import types in type filter', function () {
    actingAsRole('superadmin');

    $response = $this->get(route('admin.importazioni-monitor.index'));

    $response->assertOk();

    foreach (Importazione::availableTypes() as $type) {
        $response->assertSee('value="'.$type.'"', false);
        $response->assertSeeText(Importazione::typeLabelFor($type));
    }
});
