<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class PdfToDocxService
{
    /**
     * Convert a PDF stored on the documents disk to DOCX using LibreOffice.
     * Returns the new DOCX storage path, or null if conversion is unavailable/failed.
     */
    public function convert(string $pdfDiskPath, string $workspaceId): ?string
    {
        $libreoffice = $this->findLibreOffice();

        if (!$libreoffice) {
            return null; // LibreOffice not installed — skip silently (local dev)
        }

        $pdfLocalPath = Storage::disk('documents')->path($pdfDiskPath);

        if (!file_exists($pdfLocalPath)) {
            return null;
        }

        $tmpDir = sys_get_temp_dir() . '/rdoc_convert_' . uniqid();
        mkdir($tmpDir, 0777, true);

        $cmd    = escapeshellarg($libreoffice)
                . ' --headless --convert-to docx '
                . escapeshellarg($pdfLocalPath)
                . ' --outdir '
                . escapeshellarg($tmpDir)
                . ' 2>/dev/null';

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->cleanup($tmpDir);
            return null;
        }

        // LibreOffice names the output file the same as input with .docx extension
        $baseName    = pathinfo($pdfLocalPath, PATHINFO_FILENAME);
        $docxTmpPath = $tmpDir . '/' . $baseName . '.docx';

        if (!file_exists($docxTmpPath)) {
            $this->cleanup($tmpDir);
            return null;
        }

        // Store the converted DOCX
        $docxDiskPath = 'workspaces/' . $workspaceId . '/uploads/' . $baseName . '_converted.docx';
        Storage::disk('documents')->put($docxDiskPath, file_get_contents($docxTmpPath));

        $this->cleanup($tmpDir);

        return $docxDiskPath;
    }

    public function isAvailable(): bool
    {
        return $this->findLibreOffice() !== null;
    }

    private function findLibreOffice(): ?string
    {
        $candidates = [
            '/usr/bin/libreoffice',
            '/usr/bin/soffice',
            '/usr/lib/libreoffice/program/soffice',
            'libreoffice',
            'soffice',
        ];

        foreach ($candidates as $bin) {
            $check = shell_exec('which ' . escapeshellarg($bin) . ' 2>/dev/null');
            if (!empty(trim($check ?? ''))) {
                return trim($check);
            }
            // Direct path check
            if (str_starts_with($bin, '/') && file_exists($bin)) {
                return $bin;
            }
        }

        return null;
    }

    private function cleanup(string $dir): void
    {
        if (is_dir($dir)) {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }
}
