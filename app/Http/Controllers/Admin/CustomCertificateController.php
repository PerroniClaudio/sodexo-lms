<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewCustomCertificateRequest;
use App\Http\Requests\StoreCustomCertificateRequest;
use App\Http\Requests\UpdateCustomCertificateRequest;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CustomCertificate;
use App\Models\User;
use App\Services\Certificates\CertificateVariableResolver;
use App\Services\Certificates\DocxTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CustomCertificateController extends Controller
{
    private const STORAGE_DISK = 's3';

    public function index(): View
    {
        $certificates = CustomCertificate::query()
            ->withTrashed()
            ->orderByDesc('is_active')
            ->orderByDesc('activated_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('type');

        return view('admin.certificates.index', [
            'typeLabels' => CustomCertificate::availableTypeLabels(),
            'activeCertificates' => collect(CustomCertificate::availableTypes())
                ->mapWithKeys(fn (string $type): array => [
                    $type => $certificates->get($type, collect())->first(fn (CustomCertificate $certificate): bool => $certificate->is_active),
                ]),
            'certificateHistory' => $certificates,
        ]);
    }

    public function create(): View
    {
        return view('admin.certificates.create', [
            'certificate' => new CustomCertificate([
                'is_active' => true,
            ]),
            'typeLabels' => CustomCertificate::availableTypeLabels(),
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'placeholders' => $this->placeholders(),
        ]);
    }

    public function store(StoreCustomCertificateRequest $request): RedirectResponse
    {
        $certificate = DB::transaction(function () use ($request): CustomCertificate {
            $validated = $request->validated();
            $uploadedFile = $request->file('template');
            $path = $uploadedFile->store(
                sprintf('custom-certificates/%s', $validated['type']),
                self::STORAGE_DISK
            );

            $currentActive = CustomCertificate::query()
                ->active()
                ->ofType($validated['type'])
                ->whereNull('deleted_at')
                ->first();

            if ($currentActive !== null) {
                $currentActive->forceFill([
                    'is_active' => false,
                    'archived_at' => now(),
                ])->save();
            }

            $certificate = CustomCertificate::query()->create([
                'type' => $validated['type'],
                'name' => $this->generateVersionName($validated['type']),
                'storage_disk' => self::STORAGE_DISK,
                'template_path' => $path,
                'original_filename' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getMimeType() ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'is_active' => true,
                'course_ids' => $validated['course_ids'] ?? null,
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

        return redirect()
            ->route('admin.certificates.edit', $certificate)
            ->with('status', __('Template attestato creato con successo.'));
    }

    public function edit(CustomCertificate $customCertificate): View
    {
        return view('admin.certificates.edit', [
            'certificate' => $customCertificate,
            'typeLabels' => CustomCertificate::availableTypeLabels(),
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'placeholders' => $this->placeholders(),
            'previousVersions' => CustomCertificate::query()
                ->withTrashed()
                ->ofType($customCertificate->type)
                ->whereKeyNot($customCertificate->getKey())
                ->orderByDesc('activated_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function update(UpdateCustomCertificateRequest $request, CustomCertificate $customCertificate): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('template')) {
            $certificate = DB::transaction(function () use ($request, $validated, $customCertificate): CustomCertificate {
                CustomCertificate::query()
                    ->active()
                    ->ofType($customCertificate->type)
                    ->whereKeyNot($customCertificate->getKey())
                    ->update([
                        'is_active' => false,
                        'archived_at' => now(),
                    ]);

                $customCertificate->forceFill([
                    'is_active' => false,
                    'archived_at' => now(),
                ])->save();

                $uploadedFile = $request->file('template');
                $path = $uploadedFile->store(
                    sprintf('custom-certificates/%s', $validated['type']),
                    self::STORAGE_DISK
                );

                $newCertificate = CustomCertificate::query()->create([
                    'type' => $validated['type'],
                    'name' => $this->generateVersionName($validated['type']),
                    'storage_disk' => self::STORAGE_DISK,
                    'template_path' => $path,
                    'original_filename' => $uploadedFile->getClientOriginalName(),
                    'mime_type' => $uploadedFile->getMimeType() ?? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'is_active' => true,
                    'course_ids' => $validated['course_ids'] ?? null,
                    'replaced_by_id' => null,
                    'activated_at' => now(),
                    'archived_at' => null,
                ]);

                $customCertificate->forceFill([
                    'replaced_by_id' => $newCertificate->getKey(),
                ])->save();

                return $newCertificate;
            });

            return redirect()
                ->route('admin.certificates.edit', $certificate)
                ->with('status', __('Nuova versione del template creata con successo.'));
        }

        $customCertificate->update([
            'type' => $validated['type'],
            'course_ids' => $validated['course_ids'] ?? null,
        ]);

        return redirect()
            ->route('admin.certificates.edit', $customCertificate)
            ->with('status', __('Template attestato aggiornato con successo.'));
    }

    public function restoreVersion(CustomCertificate $customCertificate): RedirectResponse
    {
        DB::transaction(function () use ($customCertificate): void {
            CustomCertificate::query()
                ->active()
                ->ofType($customCertificate->type)
                ->whereKeyNot($customCertificate->getKey())
                ->update([
                    'is_active' => false,
                    'archived_at' => now(),
                ]);

            $customCertificate->forceFill([
                'is_active' => true,
                'activated_at' => now(),
                'archived_at' => null,
            ])->save();
        });

        return redirect()
            ->route('admin.certificates.edit', $customCertificate)
            ->with('status', __('Versione del template ripristinata con successo.'));
    }

    public function preview(CustomCertificate $customCertificate): View
    {
        return view('admin.certificates.preview', [
            'certificate' => $customCertificate,
            'courses' => Course::query()->orderBy('title')->get(['id', 'title']),
            'users' => User::query()->orderBy('name')->orderBy('surname')->get(['id', 'name', 'surname', 'fiscal_code']),
            'placeholders' => $this->placeholders(),
        ]);
    }

    public function previewDownload(
        PreviewCustomCertificateRequest $request,
        CustomCertificate $customCertificate,
        CertificateVariableResolver $certificateVariableResolver,
        DocxTemplateRenderer $docxTemplateRenderer
    ): BinaryFileResponse {
        $validated = $request->validated();

        $course = Course::query()->findOrFail($validated['course_id']);
        $user = User::query()->findOrFail($validated['user_id']);
        $enrollment = CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        $temporaryPath = $docxTemplateRenderer->renderToTemporaryPath(
            $customCertificate,
            $certificateVariableResolver->resolve($course, $user, $enrollment)
        );

        $downloadName = sprintf(
            'attestato-%s-preview-%s-%s.docx',
            $customCertificate->type,
            Str::slug($course->title),
            Str::slug(trim(sprintf('%s %s', $user->name, $user->surname)))
        );

        return response()->download($temporaryPath, $downloadName)->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(): array
    {
        return [
            '${TITOLO}' => __('Titolo del corso'),
            '${ORE}' => __('Durata del corso'),
            '${NOME_UTENTE}' => __('Nome dell\'iscritto'),
            '${COGNOME_UTENTE}' => __('Cognome dell\'iscritto'),
            '${CODICE_FISCALE_UTENTE}' => __('Codice fiscale dell\'iscritto'),
            '${DATA_COMPLETAMENTO_CORSO}' => __('Data di completamento dell\'iscrizione'),
            '${DATA_CORSO}' => __('Campo Data inizio per moduli RES/live'),
            '${ORARIO_CORSO}' => __('Campo Orario del corso per moduli RES/live'),
        ];
    }

    private function generateVersionName(string $type): string
    {
        $label = CustomCertificate::availableTypeLabels()[$type] ?? $type;

        return sprintf('%s %s', $label, now()->format('Y-m-d H:i:s'));
    }
}
