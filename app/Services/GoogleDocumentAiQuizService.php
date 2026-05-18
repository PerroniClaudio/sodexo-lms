<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\ModuleQuizDocumentUpload;
use App\Models\ModuleQuizSubmission;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GoogleDocumentAiQuizService
{
    public function __construct(
        private readonly GoogleServiceAccountTokenProvider $tokenProvider
    ) {}

    public function processDocumentUpload(ModuleQuizDocumentUpload $documentUpload): void
    {
        $documentUpload->loadMissing([
            'module.quizQuestions' => fn ($query) => $query->orderBy('id')->with([
                'answers' => fn ($answerQuery) => $answerQuery->orderBy('id'),
            ]),
        ]);

        $payload = $this->processDocument($documentUpload);
        $normalizedSubmissions = $this->normalizePayload($payload);

        DB::transaction(function () use ($documentUpload, $normalizedSubmissions, $payload): void {
            $documentUpload->submissions()->delete();

            foreach ($normalizedSubmissions as $normalized) {
                $this->storeNormalizedSubmission($documentUpload, $normalized, $payload);
            }

            $documentUpload->forceFill([
                'status' => ModuleQuizDocumentUpload::STATUS_PROCESSED,
                'provider_payload' => $payload,
                'error_message' => null,
                'processed_at' => now(),
            ])->save();
        });
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
     * @return array<int, array{
     *     page: int|null,
     *     qr: array{courseId: int, moduleId: int, userId: int},
     *     answers: array<int, array{selectedOptionKey: string, confidence: float|null}>
     * }>
     */
    private function normalizePayload(array $payload): array
    {
        $entities = collect(Arr::get($payload, 'document.entities', []));
        $qrEntities = $entities
            ->filter(fn (array $entity): bool => in_array((string) Arr::get($entity, 'type'), ['submission_qr', 'qr_code'], true))
            ->values();

        if ($qrEntities->isEmpty()) {
            throw new RuntimeException('QR non rilevato dal provider OCR.');
        }

        $submissions = $qrEntities
            ->map(function (array $entity): array {
                $qrText = (string) Arr::get($entity, 'mentionText', '');
                $qrParts = explode('*', base64_decode($qrText, true) ?: '');

                if (count($qrParts) !== 3) {
                    throw new RuntimeException('QR non valido.');
                }

                return [
                    'page' => $this->entityPage($entity),
                    'qr' => [
                        'courseId' => (int) $qrParts[0],
                        'moduleId' => (int) $qrParts[1],
                        'userId' => (int) $qrParts[2],
                    ],
                    'answers' => [],
                ];
            })
            ->values()
            ->all();

        $hasMultipleSubmissions = count($submissions) > 1;

        if ($hasMultipleSubmissions && collect($submissions)->contains(fn (array $normalized): bool => $normalized['page'] === null)) {
            throw new RuntimeException('Impossibile associare i QR alle pagine del documento.');
        }

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

            $submissionIndex = $this->submissionIndexForEntity($submissions, $entity, $hasMultipleSubmissions);

            $submissions[$submissionIndex]['answers'][$questionNumber] = [
                'selectedOptionKey' => $selectedOptionKey,
                'confidence' => Arr::has($entity, 'confidence') ? (float) $entity['confidence'] : null,
            ];
        }

        return $submissions;
    }

    /**
     * @param  array{
     *     qr: array{courseId: int, moduleId: int, userId: int},
     *     answers: array<int, array{selectedOptionKey: string, confidence: float|null}>
     * }  $normalized
     * @param  array<string, mixed>  $payload
     */
    private function storeNormalizedSubmission(ModuleQuizDocumentUpload $documentUpload, array $normalized, array $payload): void
    {
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

        $submission = ModuleQuizSubmission::query()->create([
            'module_id' => $documentUpload->module_id,
            'document_upload_id' => $documentUpload->getKey(),
            'source_type' => ModuleQuizSubmission::SOURCE_UPLOAD,
            'user_id' => $qrPayload['userId'],
            'course_enrollment_id' => $courseEnrollment->getKey(),
            'uploaded_by' => $documentUpload->uploaded_by,
            'status' => ModuleQuizSubmission::STATUS_NEEDS_REVIEW,
            'provider_payload' => $payload,
        ]);

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
    }

    /**
     * @param  array<int, array{page: int|null}>  $submissions
     */
    private function submissionIndexForEntity(array $submissions, array $entity, bool $hasMultipleSubmissions): int
    {
        if (! $hasMultipleSubmissions) {
            return 0;
        }

        $page = $this->entityPage($entity);

        if ($page === null) {
            throw new RuntimeException('Impossibile associare una risposta alla pagina del documento.');
        }

        foreach ($submissions as $index => $submission) {
            if ($submission['page'] === $page) {
                return $index;
            }
        }

        throw new RuntimeException('Risposta rilevata su una pagina senza QR associato.');
    }

    private function entityPage(array $entity): ?int
    {
        $page = Arr::get($entity, 'pageAnchor.pageRefs.0.page');

        return $page === null ? null : (int) $page;
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

        return $this->tokenProvider->cloudPlatformAccessToken();
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
