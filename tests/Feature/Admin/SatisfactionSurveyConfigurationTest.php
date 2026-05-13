<?php

use App\Models\SatisfactionSurveyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows superadmin to configure the global satisfaction survey', function () {
    actingAsRole('superadmin');

    $response = $this->put(route('admin.satisfaction-survey.update'), [
        'questions' => [
            [
                'text' => 'Quanto sei soddisfatto del corso?',
                'answers' => ['Molto', 'Abbastanza', 'Poco'],
            ],
            [
                'text' => 'Consiglieresti questo corso?',
                'answers' => ['SÃ¬', 'Forse', 'No'],
            ],
        ],
    ]);

    $response->assertRedirect(route('admin.satisfaction-survey.edit'));
    $response->assertSessionHas('status', 'Questionario di gradimento aggiornato con successo.');

    $template = SatisfactionSurveyTemplate::active();

    expect($template)->not->toBeNull();
    expect($template->questions)->toHaveCount(2);
    expect($template->questions->first()->answers)->toHaveCount(3);
});

it('forbids admins from editing the global satisfaction survey', function () {
    actingAsRole('admin');

    $this->get(route('admin.satisfaction-survey.edit'))->assertForbidden();
});
