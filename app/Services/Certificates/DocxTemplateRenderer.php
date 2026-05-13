<?php

namespace App\Services\Certificates;

use App\Models\CustomCertificate;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use RuntimeException;

class DocxTemplateRenderer
{
    /**
     * @param  array<string, string>  $variables
     */
    public function renderToTemporaryPath(CustomCertificate $customCertificate, array $variables): string
    {
        $templatePath = $this->copyTemplateToTemporaryPath($customCertificate);
        $outputPath = tempnam(sys_get_temp_dir(), 'certificate-rendered-');

        if ($outputPath === false) {
            throw new RuntimeException('Unable to allocate a temporary file for the rendered certificate.');
        }

        $templateProcessor = new TemplateProcessor($templatePath);
        $templateProcessor->setMacroChars('${', '}');

        foreach ($variables as $placeholder => $value) {
            $templateProcessor->setValue($placeholder, $value);
        }

        $templateProcessor->saveAs($outputPath);
        @unlink($templatePath);

        return $outputPath;
    }

    private function copyTemplateToTemporaryPath(CustomCertificate $customCertificate): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'certificate-template-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to allocate a temporary file for certificate rendering.');
        }

        $sourceStream = Storage::disk($customCertificate->storage_disk)->readStream($customCertificate->template_path);

        if (! is_resource($sourceStream)) {
            throw new RuntimeException('Unable to read the certificate template from storage.');
        }

        $temporaryHandle = fopen($temporaryPath, 'wb');

        if (! is_resource($temporaryHandle)) {
            fclose($sourceStream);

            throw new RuntimeException('Unable to open the temporary certificate file for writing.');
        }

        stream_copy_to_stream($sourceStream, $temporaryHandle);

        fclose($sourceStream);
        fclose($temporaryHandle);

        if (filesize($temporaryPath) === 0) {
            throw new RuntimeException('Unable to copy the certificate template to a temporary file.');
        }

        return $temporaryPath;
    }
}
