<?php

use App\Models\DocumentConversionJob;
use App\Services\CloudRunJobClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();
});

it('starts a cloud run job using a service account json stored in base64 config', function () {
    $privateKey = testPrivateKey();

    config()->set('services.cloud_run.project_id', 'labor-2023');
    config()->set('services.cloud_run.region', 'europe-west8');
    config()->set('services.cloud_run.job', 'sodexo-docx-worker');
    config()->set('services.google.service_account_json_base64', base64_encode(json_encode([
        'client_email' => 'cloud-run@example.iam.gserviceaccount.com',
        'private_key' => $privateKey,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], JSON_THROW_ON_ERROR)));

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'oauth-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        'https://run.googleapis.com/v2/projects/labor-2023/locations/europe-west8/jobs/sodexo-docx-worker:run' => Http::response([
            'name' => 'projects/labor-2023/locations/europe-west8/operations/operation-123',
            'done' => false,
            'metadata' => [
                'target' => 'sodexo-docx-worker',
            ],
        ]),
    ]);

    $documentConversionJob = (new DocumentConversionJob([
        'input_disk' => 's3',
        'input_path' => 'certificates/word/test.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/test.pdf',
    ]))->forceFill([
        'id' => 123,
    ]);

    $result = app(CloudRunJobClient::class)->runDocumentConversionJob($documentConversionJob);

    expect($result['operation_name'])->toBe('projects/labor-2023/locations/europe-west8/operations/operation-123')
        ->and($result['payload']['done'])->toBeFalse();

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://oauth2.googleapis.com/token') {
            return false;
        }

        $assertion = (string) $request['assertion'];
        $payload = json_decode(base64_decode(strtr(explode('.', $assertion)[1], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && $payload['scope'] === 'https://www.googleapis.com/auth/cloud-platform'
            && $payload['iss'] === 'cloud-run@example.iam.gserviceaccount.com';
    });

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://run.googleapis.com/v2/projects/labor-2023/locations/europe-west8/jobs/sodexo-docx-worker:run'
        && $request->hasHeader('Authorization', 'Bearer oauth-token')
        && $request['overrides']['containerOverrides'][0]['env'][0] === [
            'name' => 'DOCUMENT_CONVERSION_JOB_ID',
            'value' => '123',
        ]
        && $request['overrides']['containerOverrides'][0]['env'][2] === [
            'name' => 'DOCUMENT_CONVERSION_INPUT_PATH',
            'value' => 'certificates/word/test.docx',
        ]);
});

function testPrivateKey(): string
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key, $privateKey);

    return $privateKey;
}
