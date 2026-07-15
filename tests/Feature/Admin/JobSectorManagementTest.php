<?php

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows the manual fallback risk field on the job sector create page', function () {
    $response = $this->get(route('admin.job-sectors.create'));

    $response->assertOk()
        ->assertSeeText('Rischio manuale di fallback')
        ->assertSee('name="manual_risk_level"', escape: false);
});
