<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\ModuleQuizDocumentUpload;
use App\Models\ModuleQuizSubmission;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GoogleDocumentAiQuizService
{
    public function processDocumentUpload(ModuleQuizDocumentUpload $documentUpload): void
    {
        $documentUpload->loadMissing([
            'module.quizQuestions' => fn ($query) => $query->orderBy('id')->with([
                'answers' => fn ($answerQuery) => $answerQuery->orderBy('id'),
            ]),
        ]);

        $payload = $this->processDocument($documentUpload);
        $normalized = $this->normalizePayload($payload);
        $qrPayload = $normalized['qr'];

        if ($qrPayload['moduleId'] !== $documentUpload->module_id) {
            throw new RuntimeException('Il QR non corrisponde al modulo del caricamento.');
        }

        $courseEnrollment = CourseEnrollment::query()
            ->where('course_id', $qrPayload['courseId'])
            ->where('user_id', $qrPayload['userId'])
            ->whereNull('deleted_at')
            ->first();

        if ($courseEnrollment === null) {
            throw new RuntimeException('Nessuna iscrizione attiva trovata per il QR rilevato.');
        }

        // Crea una submission per l'utente rilevato nel documento
        $submission = ModuleQuizSubmission::create([
            'module_id' => $documentUpload->module_id,
            'document_upload_id' => $documentUpload->getKey(),
            'source_type' => ModuleQuizSubmission::SOURCE_UPLOAD,
            'user_id' => $qrPayload['userId'],
            'uploaded_by' => $documentUpload->uploaded_by,
            'status' => ModuleQuizSubmission::STATUS_NEEDS_REVIEW,
        ]);

        // Crea le risposte per questa submission
        foreach ($documentUpload->module->quizQuestions as $index => $question) {
            $questionNumber = $index + 1;
            $answerPayload = $normalized['answers'][$questionNumber] ?? null;
            $selectedOptionKey = $answerPayload['selectedOptionKey'] ?? null;
            $selectedAnswer = $selectedOptionKey !== null
                ? $question->answers->values()->get($this->optionKeyIndex($selectedOptionKey))
                : null;

            $submission->answers()->create([
                'module_quiz_question_id' => $question->getKey(),
                'module_quiz_answer_id' => $selectedAnswer?->getKey(),
                'question_number' => $questionNumber,
                'selected_option_key' => $selectedOptionKey,
                'confidence' => $answerPayload['confidence'] ?? null,
            ]);
        }

        // Aggiorna il documento come processato
        $documentUpload->forceFill([
            'status' => ModuleQuizDocumentUpload::STATUS_PROCESSED,
            'provider_payload' => $payload,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function processDocument(ModuleQuizDocumentUpload $documentUpload): array
    {
        $documentContents = Storage::disk($documentUpload->disk)->get($documentUpload->path);

        return $this->request()
            ->post($this->processorPath(), [
                'skipHumanReview' => true,
                'rawDocument' => [
                    'mimeType' => 'application/pdf',
                    'content' => base64_encode($documentContents),
                ],
            ])
            ->throw()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     qr: array{courseId: int, moduleId: int, userId: int},
     *     answers: array<int, array{selectedOptionKey: string, confidence: float|null}>
     * }
     */
    private function normalizePayload(array $payload): array
    {
        $entities = collect(Arr::get($payload, 'document.entities', []));
        $qrText = (string) Arr::get($entities->firstWhere('type', 'submission_qr'), 'mentionText', '');

        if ($qrText === '') {
            $qrText = (string) Arr::get($entities->firstWhere('type', 'qr_code'), 'mentionText', '');
        }

        if ($qrText === '') {
            throw new RuntimeException('QR non rilevato dal provider OCR.');
        }

        $qrParts = explode('*', base64_decode($qrText, true) ?: '');

        if (count($qrParts) !== 3) {
            throw new RuntimeException('QR non valido.');
        }

        $answers = [];

        foreach ($entities as $entity) {
            $type = (string) Arr::get($entity, 'type');

            if (! preg_match('/^(?:q|question)_?(\d+)$/i', $type, $matches)) {
                continue;
            }

            $questionNumber = (int) $matches[1];
            $selectedOptionKey = strtoupper(trim((string) Arr::get($entity, 'mentionText')));

            if (! in_array($selectedOptionKey, ['A', 'B', 'C', 'D'], true)) {
                continue;
            }

            $answers[$questionNumber] = [
                'selectedOptionKey' => $selectedOptionKey,
                'confidence' => Arr::has($entity, 'confidence') ? (float) $entity['confidence'] : null,
            ];
        }

        return [
            'qr' => [
                'courseId' => (int) $qrParts[0],
                'moduleId' => (int) $qrParts[1],
                'userId' => (int) $qrParts[2],
            ],
            'answers' => $answers,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->connectTimeout((int) config('services.google_document_ai.timeout.connect', 5))
            ->timeout((int) config('services.google_document_ai.timeout.request', 30))
            ->retry([200, 500, 1000], throw: true);
    }

    private function baseUrl(): string
    {
        $location = $this->requireValue(config('services.google_document_ai.location'), 'services.google_document_ai.location');

        return sprintf('https://%s-documentai.googleapis.com/v1/', $location);
    }

    private function processorPath(): string
    {
        return sprintf(
            'projects/%s/locations/%s/processors/%s:process',
            $this->requireValue(config('services.google_document_ai.project_id'), 'services.google_document_ai.project_id'),
            $this->requireValue(config('services.google_document_ai.location'), 'services.google_document_ai.location'),
            $this->requireValue(config('services.google_document_ai.processor_id'), 'services.google_document_ai.processor_id'),
        );
    }

    private function accessToken(): string
    {
        $configuredAccessToken = (string) config('services.google_document_ai.access_token');

        if ($configuredAccessToken !== '') {
            return $configuredAccessToken;
        }

        $credentialsPath = $this->requireValue(config('services.google_document_ai.credentials'), 'services.google_document_ai.credentials');

        if (! is_file($credentialsPath)) {
            throw new RuntimeException('Google Document AI credentials file not found.');
        }

        /** @var array<string, mixed> $credentials */
        $credentials = json_decode((string) file_get_contents($credentialsPath), true, flags: JSON_THROW_ON_ERROR);
        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $privateKey = (string) ($credentials['private_key'] ?? '');
        $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');

        if ($clientEmail === '' || $privateKey === '') {
            throw new RuntimeException('Google Document AI credentials are incomplete.');
        }

        return Cache::remember('google-document-ai-access-token-'.md5($clientEmail), now()->addMinutes(45), function () use ($clientEmail, $privateKey, $tokenUri): string {
            $issuedAt = now()->timestamp;
            $expiresAt = $issuedAt + 3600;

            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $payload = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => $tokenUri,
                'exp' => $expiresAt,
                'iat' => $issuedAt,
            ], JSON_THROW_ON_ERROR));

            $signatureInput = $header.'.'.$payload;
            openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

            $assertion = $signatureInput.'.'.$this->base64UrlEncode($signature);

            $response = Http::asForm()
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ])
                ->throw()
                ->json();

            $accessToken = (string) ($response['access_token'] ?? '');

            if ($accessToken === '') {
                throw new RuntimeException('Google OAuth token response is missing access_token.');
            }

            return $accessToken;
        });
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function requireValue(?string $value, string $configKey): string
    {
        if ($value === null || $value === '') {
            throw new RuntimeException(sprintf('Missing configuration value [%s].', $configKey));
        }

        return $value;
    }

    private function optionKeyIndex(string $selectedOptionKey): int
    {
        return match ($selectedOptionKey) {
            'A' => 0,
            'B' => 1,
            'C' => 2,
            'D' => 3,
            default => 0,
        };
    }
}
