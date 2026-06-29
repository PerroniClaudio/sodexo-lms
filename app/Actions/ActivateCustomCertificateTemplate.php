<?php

namespace App\Actions;

use App\Models\CustomCertificate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActivateCustomCertificateTemplate
{
    /**
     * @param  array<int>|null  $courseIds
     */
    public function handle(string $type, UploadedFile $uploadedFile, ?array $courseIds = null): CustomCertificate
    {
        return DB::transaction(function () use ($courseIds, $type, $uploadedFile): CustomCertificate {
            $path = $uploadedFile->store(sprintf('custom-certificates/%s', $type));

            $currentActive = CustomCertificate::query()
                ->active()
                ->ofType($type)
                ->whereNull('deleted_at')
                ->first();

            if ($currentActive !== null) {
                $currentActive->forceFill([
                    'is_active' => false,
                    'archived_at' => now(),
                ])->save();
            }

            $certificate = CustomCertificate::query()->create([
                'type' => $type,
                'name' => $this->generateVersionName($type),
                'storage_disk' => Storage::getDefaultDriver(),
                'template_path' => $path,
                'original_filename' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getMimeType() ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'is_active' => true,
                'course_ids' => $courseIds,
                'replaced_by_id' => null,
                'activated_at' => now(),
                'archived_at' => null,
            ]);

            if ($currentActive !== null) {
                $currentActive->forceFill([
                    'replaced_by_id' => $certificate->getKey(),
                ])->save();
            }

            return $certificate;
        });
    }

    private function generateVersionName(string $type): string
    {
        $label = CustomCertificate::availableTypeLabels()[$type] ?? $type;

        return sprintf('%s %s', $label, now()->format('Y-m-d H:i:s'));
    }
}
