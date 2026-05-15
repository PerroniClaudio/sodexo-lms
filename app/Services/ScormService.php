<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ScormPackage;
use App\Models\ScormSession;
use App\Models\ScormTracking;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ScormService
{
    private const STORAGE_DISK = 'local';

    private const STORAGE_ROOT = 'scorm';

    /**
     * @var array<string, string>
     */
    private const ERROR_STRINGS = [
        '0' => 'No error',
        '101' => 'General exception',
        '201' => 'Invalid argument error',
        '301' => 'Not initialized',
        '391' => 'General commit failure',
        '401' => 'Not implemented error',
    ];

    public function storeUploadedPackage(
        Module $module,
        UploadedFile $uploadedFile,
        ?string $title = null,
        ?string $description = null,
    ): ScormPackage {
        $disk = Storage::disk(self::STORAGE_DISK);
        $directory = sprintf('%s/packages/module-%d/%s', self::STORAGE_ROOT, $module->getKey(), Str::uuid()->toString());
        $filePath = sprintf('%s/source.zip', $directory);
        $extractedPath = sprintf('%s/extracted', $directory);

        $disk->putFileAs($directory, $uploadedFile, 'source.zip');

        $package = ScormPackage::query()->create([
            'course_id' => (int) $module->belongsTo,
            'module_id' => $module->getKey(),
            'title' => $title ?: $module->title,
            'description' => $description,
            'file_path' => $filePath,
            'extracted_path' => $extractedPath,
            'status' => ScormPackage::STATUS_PROCESSING,
        ]);

        try {
            $manifest = $this->extractPackageArchive($uploadedFile, $extractedPath);
            $parsedManifest = $this->parseManifest(
                $disk->path(sprintf('%s/%s', $extractedPath, $manifest['path'])),
                $manifest['path'],
            );

            $package->forceFill([
                'title' => $title ?: ($parsedManifest['title'] ?: $module->title),
                'description' => $description ?: $parsedManifest['description'],
                'version' => $parsedManifest['version'],
                'identifier' => $parsedManifest['identifier'],
                'entry_point' => $parsedManifest['entry_point'],
                'manifest_data' => $parsedManifest['manifest_data'],
                'sco_data' => $parsedManifest['sco_data'],
                'status' => ScormPackage::STATUS_READY,
                'error_message' => null,
            ])->save();

            return $package->fresh();
        } catch (Throwable $exception) {
            $this->cleanupPackage($package);

            $package->forceFill([
                'status' => ScormPackage::STATUS_ERROR,
                'error_message' => $exception->getMessage(),
            ])->save();

            Log::error('SCORM package processing failed.', [
                'package_id' => $package->getKey(),
                'module_id' => $module->getKey(),
                'course_id' => $module->belongsTo,
                'exception_class' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function deletePackage(ScormPackage $package): void
    {
        $disk = Storage::disk(self::STORAGE_DISK);

        if ($package->file_path !== null) {
            $disk->delete($package->file_path);
        }

        if ($package->extracted_path !== null) {
            $disk->deleteDirectory($package->extracted_path);

            $rootDirectory = Str::beforeLast($package->extracted_path, '/extracted');

            if ($rootDirectory !== '' && $rootDirectory !== $package->extracted_path) {
                $disk->deleteDirectory($rootDirectory);
            }
        }

        $package->delete();
    }

    public function initializeRuntime(
        User $user,
        ScormPackage $package,
        ModuleProgress $moduleProgress,
        string $scoIdentifier,
        string $sessionId,
    ): array {
        $session = $this->resolveSession($user, $package, $moduleProgress, $scoIdentifier, $sessionId);
        $state = $this->getRuntimeSnapshot($user, $package, $scoIdentifier, $moduleProgress);

        if ($moduleProgress->status === ModuleProgress::STATUS_AVAILABLE) {
            $moduleProgress->start();
            $moduleProgress->refresh();
        } elseif ($moduleProgress->status !== ModuleProgress::STATUS_LOCKED) {
            $moduleProgress->forceFill([
                'started_at' => $moduleProgress->started_at ?? now(),
                'last_accessed_at' => now(),
            ])->save();

            $moduleProgress->courseEnrollment()->firstOrFail()->markAsInProgress();
        }

        $session->forceFill([
            'status' => ScormSession::STATUS_ACTIVE,
            'runtime_snapshot' => $state,
            'last_error_code' => '0',
            'initialized_at' => $session->initialized_at ?? now(),
            'last_activity_at' => now(),
        ])->save();

        return $state;
    }

    public function getRuntimeSnapshot(
        User $user,
        ScormPackage $package,
        string $scoIdentifier,
        ?ModuleProgress $moduleProgress = null,
    ): array {
        $state = $this->rebuildCurrentState($user, $package, $scoIdentifier);

        return array_merge(
            $this->defaultRuntimeState($package, $moduleProgress, $state),
            $state,
        );
    }

    public function getLastValue(
        User $user,
        ScormPackage $package,
        string $scoIdentifier,
        string $element,
    ): ?string {
        return ScormTracking::query()
            ->where('user_id', $user->getKey())
            ->where('scorm_package_id', $package->getKey())
            ->where('sco_identifier', $scoIdentifier)
            ->where('element', $element)
            ->orderByDesc('tracked_at')
            ->orderByDesc('id')
            ->value('value');
    }

    public function persistTrackingValue(
        User $user,
        ScormPackage $package,
        string $scoIdentifier,
        string $element,
        ?string $value,
        ?string $sessionId = null,
        ?ModuleProgress $moduleProgress = null,
    ): ScormTracking {
        $tracking = ScormTracking::query()->create([
            'user_id' => $user->getKey(),
            'scorm_package_id' => $package->getKey(),
            'sco_identifier' => $scoIdentifier,
            'element' => $element,
            'value' => $value,
            'tracked_at' => now(),
            'session_id' => $sessionId,
        ]);

        $session = $sessionId !== null
            ? ScormSession::query()
                ->where('session_id', $sessionId)
                ->where('user_id', $user->getKey())
                ->where('scorm_package_id', $package->getKey())
                ->first()
            : null;

        if ($session !== null) {
            $runtimeSnapshot = $session->runtime_snapshot ?? [];
            $runtimeSnapshot[$element] = $value;

            $session->forceFill([
                'runtime_snapshot' => $runtimeSnapshot,
                'last_activity_at' => now(),
                'last_error_code' => '0',
            ])->save();
        }

        if ($moduleProgress !== null) {
            $this->syncProgressFromState(
                $moduleProgress,
                $this->getRuntimeSnapshot($user, $package, $scoIdentifier, $moduleProgress),
            );
        }

        return $tracking;
    }

    public function commitRuntime(
        User $user,
        ScormPackage $package,
        ModuleProgress $moduleProgress,
        string $scoIdentifier,
        string $sessionId,
        array $values = [],
    ): array {
        return DB::transaction(function () use ($user, $package, $moduleProgress, $scoIdentifier, $sessionId, $values): array {
            foreach ($values as $element => $value) {
                $this->persistTrackingValue(
                    $user,
                    $package,
                    $scoIdentifier,
                    (string) $element,
                    $value === null ? null : (string) $value,
                    $sessionId,
                );
            }

            $session = $this->resolveSession($user, $package, $moduleProgress, $scoIdentifier, $sessionId);
            $snapshot = $this->getRuntimeSnapshot($user, $package, $scoIdentifier, $moduleProgress);

            $this->synchronizeSessionTime($user, $moduleProgress, $package, $session, $snapshot);
            $this->syncProgressFromState($moduleProgress, $snapshot);

            $session->forceFill([
                'runtime_snapshot' => $snapshot,
                'last_activity_at' => now(),
                'last_error_code' => '0',
            ])->save();

            return $snapshot;
        });
    }

    public function terminateRuntime(
        User $user,
        ScormPackage $package,
        ModuleProgress $moduleProgress,
        string $scoIdentifier,
        string $sessionId,
        array $values = [],
    ): array {
        $snapshot = $this->commitRuntime($user, $package, $moduleProgress, $scoIdentifier, $sessionId, $values);

        ScormSession::query()
            ->where('session_id', $sessionId)
            ->where('user_id', $user->getKey())
            ->where('scorm_package_id', $package->getKey())
            ->update([
                'status' => ScormSession::STATUS_TERMINATED,
                'terminated_at' => now(),
                'last_activity_at' => now(),
                'last_error_code' => '0',
            ]);

        return $snapshot;
    }

    public function getErrorString(string $code): string
    {
        return self::ERROR_STRINGS[$code] ?? self::ERROR_STRINGS['101'];
    }

    public function getDiagnostic(string $code): string
    {
        return sprintf('%s (%s)', $this->getErrorString($code), $code);
    }

    /**
     * @return array{path: string}
     */
    private function extractPackageArchive(UploadedFile $uploadedFile, string $extractedPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($uploadedFile->getRealPath()) !== true) {
            throw new RuntimeException('Unable to open the uploaded SCORM archive.');
        }

        $disk = Storage::disk(self::STORAGE_DISK);
        $manifestPath = null;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entryName = $zip->getNameIndex($index);

            if ($entryName === false) {
                continue;
            }

            $normalizedPath = $this->normalizeArchivePath($entryName);

            if ($normalizedPath === '') {
                continue;
            }

            if ($this->shouldIgnoreArchiveEntry($normalizedPath)) {
                continue;
            }

            if (str_ends_with($entryName, '/')) {
                $disk->makeDirectory(sprintf('%s/%s', $extractedPath, $normalizedPath));

                continue;
            }

            $stream = $zip->getStream($entryName);

            if ($stream === false) {
                throw new RuntimeException(sprintf('Unable to extract archive entry [%s].', $entryName));
            }

            $targetPath = sprintf('%s/%s', $extractedPath, $normalizedPath);
            $disk->put($targetPath, stream_get_contents($stream));
            fclose($stream);

            if (Str::lower(basename($normalizedPath)) === 'imsmanifest.xml') {
                $manifestPath = $normalizedPath;
            }
        }

        $zip->close();

        if ($manifestPath === null) {
            throw new RuntimeException('The uploaded archive does not contain imsmanifest.xml.');
        }

        return [
            'path' => $manifestPath,
        ];
    }

    /**
     * @return array{
     *     identifier: ?string,
     *     title: ?string,
     *     description: ?string,
     *     version: string,
     *     entry_point: string,
     *     manifest_data: array<string, mixed>,
     *     sco_data: array<int, array<string, mixed>>
     * }
     */
    private function parseManifest(string $absoluteManifestPath, string $manifestRelativePath): array
    {
        $dom = new \DOMDocument;
        $previousUseErrors = libxml_use_internal_errors(true);

        try {
            if (! $dom->load($absoluteManifestPath)) {
                throw new RuntimeException('Unable to parse imsmanifest.xml.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
        }

        $xpath = new \DOMXPath($dom);
        $manifest = $dom->documentElement;

        if ($manifest === null) {
            throw new RuntimeException('The SCORM manifest is empty.');
        }

        $resources = $this->parseResources($xpath, $manifestRelativePath);
        $organizations = $this->parseOrganizations($xpath);
        $defaultOrganization = trim((string) $xpath->evaluate('string((/*[local-name()="manifest"]/*[local-name()="organizations"]/@default)[1])')) ?: null;
        $version = $this->detectVersion($xpath, $dom);
        $identifier = $manifest->getAttribute('identifier') ?: null;
        $title = trim((string) $xpath->evaluate('string((/*[local-name()="manifest"]/*[local-name()="organizations"]/*[local-name()="organization"]/*[local-name()="title"])[1])')) ?: null;
        $description = trim((string) $xpath->evaluate('string((/*[local-name()="manifest"]/*[local-name()="metadata"]//*[local-name()="description"])[1])')) ?: null;
        $launchableItem = $this->resolveLaunchableItem($organizations, $resources, $defaultOrganization);

        if ($launchableItem === null || empty($launchableItem['entry_point'])) {
            throw new RuntimeException('Unable to determine a valid SCORM entry point from the default organization.');
        }

        return [
            'identifier' => $identifier,
            'title' => $title,
            'description' => $description,
            'version' => $version,
            'entry_point' => $launchableItem['entry_point'],
            'manifest_data' => [
                'identifier' => $identifier,
                'version' => $version,
                'default_organization' => $defaultOrganization ?: null,
                'organizations' => $organizations,
                'resources' => array_values($resources),
            ],
            'sco_data' => $this->buildScoData($organizations, $resources),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function parseResources(\DOMXPath $xpath, string $manifestRelativePath): array
    {
        $resources = [];
        $manifestDirectory = trim(dirname($manifestRelativePath), './');

        foreach ($xpath->query('/*[local-name()="manifest"]/*[local-name()="resources"]/*[local-name()="resource"]') as $resourceNode) {
            if (! $resourceNode instanceof \DOMElement) {
                continue;
            }

            $identifier = $resourceNode->getAttribute('identifier');
            $href = $resourceNode->getAttribute('href') ?: null;
            $entryPoint = $href === null ? null : $this->normalizeRelativePath($manifestDirectory, $href);

            $resources[$identifier] = [
                'identifier' => $identifier,
                'type' => $resourceNode->getAttribute('type') ?: null,
                'href' => $href,
                'entry_point' => $entryPoint,
                'scorm_type' => $resourceNode->getAttributeNS('*', 'scormType')
                    ?: $resourceNode->getAttributeNS('*', 'scormtype')
                    ?: null,
                'files' => $this->parseResourceFiles($resourceNode, $manifestDirectory),
            ];
        }

        return $resources;
    }

    /**
     * @return array<int, string>
     */
    private function parseResourceFiles(\DOMElement $resourceNode, string $manifestDirectory): array
    {
        $files = [];

        foreach ($resourceNode->getElementsByTagName('*') as $childNode) {
            if (! $childNode instanceof \DOMElement || $childNode->localName !== 'file') {
                continue;
            }

            $href = $childNode->getAttribute('href');

            if ($href === '') {
                continue;
            }

            $files[] = $this->normalizeRelativePath($manifestDirectory, $href);
        }

        return array_values(array_unique(array_filter($files)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseOrganizations(\DOMXPath $xpath): array
    {
        $organizations = [];

        foreach ($xpath->query('/*[local-name()="manifest"]/*[local-name()="organizations"]/*[local-name()="organization"]') as $organizationNode) {
            if (! $organizationNode instanceof \DOMElement) {
                continue;
            }

            $organizations[] = [
                'identifier' => $organizationNode->getAttribute('identifier') ?: null,
                'title' => trim((string) $xpath->evaluate('string(./*[local-name()="title"][1])', $organizationNode)) ?: null,
                'items' => $this->parseItems($organizationNode),
            ];
        }

        return $organizations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseItems(\DOMElement $parent): array
    {
        $items = [];

        foreach ($parent->childNodes as $childNode) {
            if (! $childNode instanceof \DOMElement || $childNode->localName !== 'item') {
                continue;
            }

            $items[] = [
                'identifier' => $childNode->getAttribute('identifier') ?: null,
                'identifierref' => $childNode->getAttribute('identifierref') ?: null,
                'title' => trim((string) $childNode->getElementsByTagName('title')->item(0)?->textContent) ?: null,
                'parameters' => $childNode->getAttributeNS('*', 'parameters') ?: null,
                'isvisible' => $childNode->getAttribute('isvisible') !== 'false',
                'items' => $this->parseItems($childNode),
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $organizations
     * @param  array<string, array<string, mixed>>  $resources
     * @return array<string, mixed>|null
     */
    private function resolveLaunchableItem(array $organizations, array $resources, ?string $defaultOrganization): ?array
    {
        $orderedOrganizations = collect($organizations)->values();

        if ($defaultOrganization !== null && $defaultOrganization !== '') {
            $defaultMatch = $orderedOrganizations
                ->first(fn (array $organization): bool => $organization['identifier'] === $defaultOrganization);

            if ($defaultMatch !== null) {
                $orderedOrganizations = collect([$defaultMatch])
                    ->concat(
                        $orderedOrganizations->reject(
                            fn (array $organization): bool => $organization['identifier'] === $defaultOrganization,
                        ),
                    )
                    ->values();
            }
        }

        foreach ($orderedOrganizations as $organization) {
            $launchableItem = $this->findLaunchableItem($organization['items'] ?? [], $resources);

            if ($launchableItem !== null) {
                return $launchableItem;
            }
        }

        foreach ($resources as $resource) {
            if (! empty($resource['entry_point'])) {
                return [
                    'identifier' => $resource['identifier'],
                    'title' => null,
                    'resource_identifier' => $resource['identifier'],
                    'entry_point' => $resource['entry_point'],
                    'scorm_type' => $resource['scorm_type'],
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $resources
     * @return array<string, mixed>|null
     */
    private function findLaunchableItem(array $items, array $resources): ?array
    {
        foreach ($items as $item) {
            $resourceIdentifier = $item['identifierref'] ?? null;
            $resource = $resourceIdentifier !== null ? ($resources[$resourceIdentifier] ?? null) : null;

            if ($resource !== null && ! empty($resource['entry_point'])) {
                return [
                    'identifier' => $item['identifier'],
                    'title' => $item['title'],
                    'resource_identifier' => $resourceIdentifier,
                    'entry_point' => $resource['entry_point'],
                    'scorm_type' => $resource['scorm_type'],
                ];
            }

            $nestedItem = $this->findLaunchableItem($item['items'] ?? [], $resources);

            if ($nestedItem !== null) {
                return $nestedItem;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $organizations
     * @param  array<string, array<string, mixed>>  $resources
     * @return array<int, array<string, mixed>>
     */
    private function buildScoData(array $organizations, array $resources): array
    {
        $scoData = [];

        foreach ($organizations as $organization) {
            $this->appendScoItems($scoData, $organization['items'] ?? [], $resources);
        }

        return array_values($scoData);
    }

    /**
     * @param  array<int, array<string, mixed>>  $scoData
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, array<string, mixed>>  $resources
     */
    private function appendScoItems(array &$scoData, array $items, array $resources): void
    {
        foreach ($items as $item) {
            $resourceIdentifier = $item['identifierref'] ?? null;
            $resource = $resourceIdentifier !== null ? ($resources[$resourceIdentifier] ?? null) : null;

            if ($resource !== null && ! empty($resource['entry_point'])) {
                $scoData[] = [
                    'identifier' => $item['identifier'],
                    'title' => $item['title'],
                    'resource_identifier' => $resourceIdentifier,
                    'entry_point' => $resource['entry_point'],
                    'scorm_type' => $resource['scorm_type'],
                    'files' => $resource['files'],
                ];
            }

            $this->appendScoItems($scoData, $item['items'] ?? [], $resources);
        }
    }

    private function detectVersion(\DOMXPath $xpath, \DOMDocument $dom): string
    {
        $schemaVersion = trim((string) $xpath->evaluate('string((/*[local-name()="manifest"]/*[local-name()="metadata"]/*[local-name()="schemaversion"])[1])'));

        if (Str::contains($schemaVersion, '2004')) {
            return '2004';
        }

        if (Str::contains($schemaVersion, '1.2')) {
            return '1.2';
        }

        foreach ($dom->getElementsByTagName('*') as $node) {
            if (! $node instanceof \DOMElement) {
                continue;
            }

            $namespaceUri = $node->namespaceURI ?? '';

            if (Str::contains($namespaceUri, ['adlcp_v1p3', 'adlseq_v1p3', 'imsss_v1p0'])) {
                return '2004';
            }

            if (Str::contains($namespaceUri, ['adlcp_rootv1p2', 'adlcp_rootv1p1p2'])) {
                return '1.2';
            }
        }

        return '1.2';
    }

    private function resolveSession(
        User $user,
        ScormPackage $package,
        ModuleProgress $moduleProgress,
        string $scoIdentifier,
        string $sessionId,
    ): ScormSession {
        return ScormSession::query()->firstOrCreate(
            [
                'session_id' => $sessionId,
            ],
            [
                'user_id' => $user->getKey(),
                'course_user_id' => $moduleProgress->course_user_id,
                'module_id' => $moduleProgress->module_id,
                'scorm_package_id' => $package->getKey(),
                'sco_identifier' => $scoIdentifier,
                'status' => ScormSession::STATUS_ACTIVE,
                'runtime_snapshot' => [],
                'last_error_code' => '0',
            ],
        );
    }

    /**
     * @return array<string, ?string>
     */
    private function rebuildCurrentState(User $user, ScormPackage $package, string $scoIdentifier): array
    {
        return ScormTracking::query()
            ->where('user_id', $user->getKey())
            ->where('scorm_package_id', $package->getKey())
            ->where('sco_identifier', $scoIdentifier)
            ->orderBy('tracked_at')
            ->orderBy('id')
            ->get()
            ->reduce(function (array $state, ScormTracking $tracking): array {
                $state[$tracking->element] = $tracking->value;

                return $state;
            }, []);
    }

    /**
     * @param  array<string, ?string>  $existingState
     * @return array<string, ?string>
     */
    private function defaultRuntimeState(
        ScormPackage $package,
        ?ModuleProgress $moduleProgress,
        array $existingState,
    ): array {
        $status = $this->deriveStatusFromProgress($moduleProgress);
        $version = $package->version === '2004' ? '2004' : '1.2';
        $resume = Arr::hasAny($existingState, ['cmi.core.lesson_location', 'cmi.suspend_data', 'cmi.location']);

        if ($version === '2004') {
            return [
                'cmi.location' => $existingState['cmi.location'] ?? '',
                'cmi.suspend_data' => $existingState['cmi.suspend_data'] ?? '',
                'cmi.completion_status' => $existingState['cmi.completion_status'] ?? $status['completion_status'],
                'cmi.success_status' => $existingState['cmi.success_status'] ?? $status['success_status'],
                'cmi.score.raw' => $existingState['cmi.score.raw'] ?? null,
                'cmi.score.min' => $existingState['cmi.score.min'] ?? '0',
                'cmi.score.max' => $existingState['cmi.score.max'] ?? (string) ($moduleProgress?->module?->max_score ?? 100),
                'cmi.total_time' => $existingState['cmi.total_time'] ?? $this->formatTotalTime($moduleProgress?->time_spent_seconds ?? 0, '2004'),
                'cmi.entry' => $existingState['cmi.entry'] ?? ($resume ? 'resume' : 'ab-initio'),
                'cmi.mode' => $existingState['cmi.mode'] ?? 'normal',
            ];
        }

        return [
            'cmi.core.lesson_location' => $existingState['cmi.core.lesson_location'] ?? '',
            'cmi.suspend_data' => $existingState['cmi.suspend_data'] ?? '',
            'cmi.core.lesson_status' => $existingState['cmi.core.lesson_status'] ?? $status['lesson_status'],
            'cmi.core.score.raw' => $existingState['cmi.core.score.raw'] ?? null,
            'cmi.core.score.min' => $existingState['cmi.core.score.min'] ?? '0',
            'cmi.core.score.max' => $existingState['cmi.core.score.max'] ?? (string) ($moduleProgress?->module?->max_score ?? 100),
            'cmi.core.total_time' => $existingState['cmi.core.total_time'] ?? $this->formatTotalTime($moduleProgress?->time_spent_seconds ?? 0, '1.2'),
            'cmi.core.entry' => $existingState['cmi.core.entry'] ?? ($resume ? 'resume' : 'ab-initio'),
            'cmi.core.lesson_mode' => $existingState['cmi.core.lesson_mode'] ?? 'normal',
            'cmi.core.credit' => $existingState['cmi.core.credit'] ?? 'credit',
        ];
    }

    /**
     * @return array{lesson_status: string, completion_status: string, success_status: string}
     */
    private function deriveStatusFromProgress(?ModuleProgress $moduleProgress): array
    {
        return match ($moduleProgress?->status) {
            ModuleProgress::STATUS_COMPLETED => [
                'lesson_status' => 'completed',
                'completion_status' => 'completed',
                'success_status' => 'passed',
            ],
            ModuleProgress::STATUS_FAILED => [
                'lesson_status' => 'failed',
                'completion_status' => 'incomplete',
                'success_status' => 'failed',
            ],
            ModuleProgress::STATUS_IN_PROGRESS => [
                'lesson_status' => 'incomplete',
                'completion_status' => 'incomplete',
                'success_status' => 'unknown',
            ],
            default => [
                'lesson_status' => 'not attempted',
                'completion_status' => 'not attempted',
                'success_status' => 'unknown',
            ],
        };
    }

    /**
     * @param  array<string, ?string>  $state
     */
    private function syncProgressFromState(ModuleProgress $moduleProgress, array $state): void
    {
        $status = $state['cmi.core.lesson_status']
            ?? $state['cmi.completion_status']
            ?? $state['cmi.success_status']
            ?? null;

        if ($status === null) {
            return;
        }

        if (in_array($status, ['completed', 'passed'], true)) {
            if ($moduleProgress->status !== ModuleProgress::STATUS_COMPLETED) {
                $moduleProgress->markCompleted();
            }

            return;
        }

        if ($status === 'failed' || ($state['cmi.success_status'] ?? null) === 'failed') {
            $moduleProgress->forceFill([
                'status' => ModuleProgress::STATUS_FAILED,
                'started_at' => $moduleProgress->started_at ?? now(),
                'last_accessed_at' => now(),
            ])->save();

            $courseEnrollment = $moduleProgress->courseEnrollment()->firstOrFail();
            $courseEnrollment->markAsInProgress();
            $courseEnrollment->syncProgressState();

            return;
        }

        if (in_array($status, ['incomplete', 'browsed'], true)) {
            if ($moduleProgress->status === ModuleProgress::STATUS_AVAILABLE) {
                $moduleProgress->start();

                return;
            }

            $moduleProgress->forceFill([
                'started_at' => $moduleProgress->started_at ?? now(),
                'last_accessed_at' => now(),
                'status' => $moduleProgress->status === ModuleProgress::STATUS_LOCKED
                    ? ModuleProgress::STATUS_LOCKED
                    : ModuleProgress::STATUS_IN_PROGRESS,
            ])->save();

            $moduleProgress->courseEnrollment()->firstOrFail()->markAsInProgress();
        }
    }

    /**
     * @param  array<string, ?string>  $snapshot
     */
    private function synchronizeSessionTime(
        User $user,
        ModuleProgress $moduleProgress,
        ScormPackage $package,
        ScormSession $session,
        array $snapshot,
    ): void {
        $sessionTime = $snapshot['cmi.core.session_time'] ?? $snapshot['cmi.session_time'] ?? null;

        if ($sessionTime === null || $sessionTime === '') {
            return;
        }

        $seconds = $this->parseSessionTime($sessionTime, $package->version);

        if ($seconds <= $session->recorded_session_seconds) {
            return;
        }

        $delta = $seconds - $session->recorded_session_seconds;

        $moduleProgress->forceFill([
            'time_spent_seconds' => max(0, $moduleProgress->time_spent_seconds + $delta),
            'last_accessed_at' => now(),
            'started_at' => $moduleProgress->started_at ?? now(),
        ])->save();

        $moduleProgress->courseEnrollment()->firstOrFail()->forceFill([
            'last_accessed_at' => now(),
        ])->save();

        $session->forceFill([
            'recorded_session_seconds' => $seconds,
        ])->save();

        $totalTimeElement = $package->version === '2004' ? 'cmi.total_time' : 'cmi.core.total_time';

        $this->persistTrackingValue(
            $user,
            $package,
            $session->sco_identifier,
            $totalTimeElement,
            $this->formatTotalTime($moduleProgress->fresh()->time_spent_seconds, $package->version ?? '1.2'),
            $session->session_id,
        );
    }

    private function parseSessionTime(string $value, ?string $version): int
    {
        if (Str::startsWith($value, 'P')) {
            $interval = new \DateInterval($value);

            return ($interval->d * 86400)
                + ($interval->h * 3600)
                + ($interval->i * 60)
                + $interval->s;
        }

        if (! preg_match('/^(?<hours>\d{2,4}):(?<minutes>\d{2}):(?<seconds>\d{2})(?:\.\d+)?$/', $value, $matches)) {
            throw new RuntimeException(sprintf('Unsupported session time format [%s] for SCORM %s.', $value, $version ?? 'unknown'));
        }

        return ((int) $matches['hours'] * 3600) + ((int) $matches['minutes'] * 60) + (int) $matches['seconds'];
    }

    private function formatTotalTime(int $seconds, string $version): string
    {
        $seconds = max(0, $seconds);

        if ($version === '2004') {
            return sprintf('PT%dH%dM%dS', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }

        return sprintf('%04d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    private function normalizeArchivePath(string $path): string
    {
        $normalizedPath = str_replace('\\', '/', trim($path));
        $normalizedPath = preg_replace('#/+#', '/', $normalizedPath) ?: '';
        $normalizedPath = ltrim($normalizedPath, '/');
        $normalizedPath = rtrim($normalizedPath, '/');

        if ($normalizedPath === '') {
            return '';
        }

        if (Str::contains($normalizedPath, ["\0", '../', '..\\'])) {
            throw new RuntimeException(sprintf('Archive entry [%s] is not allowed.', $path));
        }

        foreach (explode('/', $normalizedPath) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException(sprintf('Archive entry [%s] contains an invalid path segment.', $path));
            }
        }

        return $normalizedPath;
    }

    private function shouldIgnoreArchiveEntry(string $path): bool
    {
        $segments = explode('/', $path);
        $basename = basename($path);

        if (in_array('__MACOSX', $segments, true)) {
            return true;
        }

        if ($basename === '.DS_Store') {
            return true;
        }

        return Str::startsWith($basename, '._');
    }

    private function normalizeRelativePath(string $basePath, string $relativePath): string
    {
        $combinedPath = trim($basePath === '' ? $relativePath : sprintf('%s/%s', $basePath, $relativePath), '/');
        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $combinedPath)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw new RuntimeException(sprintf('Resolved path [%s] escapes the SCORM package root.', $relativePath));
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function cleanupPackage(ScormPackage $package): void
    {
        $disk = Storage::disk(self::STORAGE_DISK);

        if ($package->file_path !== null) {
            $disk->delete($package->file_path);
        }

        if ($package->extracted_path !== null) {
            $disk->deleteDirectory($package->extracted_path);
        }
    }
}
