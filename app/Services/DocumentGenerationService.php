<?php

namespace App\Services;

use App\Models\GeneratedDocument;
use App\Models\Template;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class DocumentGenerationService
{
    /**
     * Generate a personalized document from a template and user-provided values.
     */
    public function generate(Template $template, array $values): GeneratedDocument
    {
        $template->load(['uploadedDocument']);
        $doc = $template->uploadedDocument;

        if ($doc && $doc->isDocx()) {
            // DOCX path: only need variables, not position data
            $template->load(['approvedVariables']);
            return $this->generateFromDocx($template, $doc, $values);
        }

        if ($doc && $doc->isPdf()) {
            // PDF overlay path: load occurrence position data for accurate placement
            $template->load(['approvedVariables.activeOccurrences']);
            return $this->generateFromPdfOverlay($template, $doc, $values);
        }

        // Fallback HTML path
        $template->load(['approvedVariables']);
        return $this->generateFromHtml($template, $values);
    }

    // ── DOCX generation via TemplateProcessor (preserves all formatting) ──

    private function generateFromDocx(Template $template, $doc, array $values): GeneratedDocument
    {
        // Use the pre-built template DOCX with ${var_name} placeholders if available
        $templatePath = $template->template_docx_path
            ? Storage::disk('documents')->path($template->template_docx_path)
            : null;

        if (!$templatePath || !file_exists($templatePath)) {
            // Fallback: raw XML replacement on the original file
            return $this->generateFromDocxRaw($template, $doc, $values);
        }

        $processor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

        foreach ($template->approvedVariables as $var) {
            $value = $values[$var->name] ?? '';
            if ($value !== null && $value !== '') {
                $processor->setValue($var->name, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            }
        }

        $tmpOutput = tempnam(sys_get_temp_dir(), 'rdoc_out_') . '.docx';
        $processor->saveAs($tmpOutput);

        $docxBytes = file_get_contents($tmpOutput);
        unlink($tmpOutput);

        $fileName = Str::slug($template->name) . '-' . now()->format('Ymd-His') . '.docx';
        $path     = 'workspaces/' . $template->workspace_id . '/generated/' . $fileName;
        Storage::disk('documents')->put($path, $docxBytes);

        return GeneratedDocument::create([
            'workspace_id'    => $template->workspace_id,
            'user_id'         => auth()->id(),
            'template_id'     => $template->id,
            'variable_values' => $values,
            'file_path'       => $path,
            'file_name'       => $fileName,
            'disk'            => 'documents',
            'status'          => 'ready',
        ]);
    }

    private function generateFromDocxRaw(Template $template, $doc, array $values): GeneratedDocument
    {
        $sourcePath = Storage::disk($doc->disk)->path($doc->path);
        $tmpFile    = tempnam(sys_get_temp_dir(), 'rdoc_') . '.docx';
        copy($sourcePath, $tmpFile);

        $zip = new ZipArchive();
        if ($zip->open($tmpFile) !== true) {
            throw new \RuntimeException('Could not open DOCX for editing.');
        }

        foreach (['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml'] as $entry) {
            $xml = $zip->getFromName($entry);
            if ($xml === false) continue;

            foreach ($template->approvedVariables as $var) {
                $newValue = $values[$var->name] ?? '';
                if ($newValue === '' || $newValue === null || empty($var->example_value)) continue;
                $xml = str_replace($var->example_value, $newValue, $xml);
                $escaped = htmlspecialchars($var->example_value, ENT_XML1 | ENT_QUOTES);
                if ($escaped !== $var->example_value) {
                    $xml = str_replace($escaped, htmlspecialchars($newValue, ENT_XML1 | ENT_QUOTES), $xml);
                }
            }

            $zip->addFromString($entry, $xml);
        }

        $zip->close();

        $docxBytes = file_get_contents($tmpFile);
        unlink($tmpFile);

        $fileName = Str::slug($template->name) . '-' . now()->format('Ymd-His') . '.docx';
        $path     = 'workspaces/' . $template->workspace_id . '/generated/' . $fileName;
        Storage::disk('documents')->put($path, $docxBytes);

        return GeneratedDocument::create([
            'workspace_id'    => $template->workspace_id,
            'user_id'         => auth()->id(),
            'template_id'     => $template->id,
            'variable_values' => $values,
            'file_path'       => $path,
            'file_name'       => $fileName,
            'disk'            => 'documents',
            'status'          => 'ready',
        ]);
    }

    // ── PDF overlay generation (preserves original PDF formatting) ───

    private function generateFromPdfOverlay(Template $template, $doc, array $values): GeneratedDocument
    {
        $pdfPath = Storage::disk($doc->disk)->path($doc->path);
        $tmpDir  = sys_get_temp_dir() . '/rdoc_gen_' . uniqid();
        mkdir($tmpDir, 0777, true);

        try {
            exec('pdftoppm -r 150 -png ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($tmpDir . '/page') . ' 2>/dev/null');

            $images = glob($tmpDir . '/page-*.png') ?: glob($tmpDir . '/page*.png') ?: [];
            natsort($images);
            $images = array_values($images);

            if (empty($images)) {
                return $this->generateFromHtml($template, $values);
            }

            $firstSize = getimagesize($images[0]);
            if (!$firstSize) {
                return $this->generateFromHtml($template, $values);
            }
            $initMmW = $firstSize[0] * 25.4 / 150;
            $initMmH = $firstSize[1] * 25.4 / 150;

            // Subclass zeroes FPDF's default ~1mm horizontal cell margin
            $pdf = new class('P', 'mm', [$initMmW, $initMmH]) extends \FPDF {
                public function __construct($orientation, $unit, $size) {
                    parent::__construct($orientation, $unit, $size);
                    $this->cMargin = 0;
                }
            };
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);

            foreach ($images as $idx => $imgPath) {
                $pageNum = $idx + 1;

                $size = getimagesize($imgPath);
                if (!$size) {
                    continue;
                }
                $pageMmW = $size[0] * 25.4 / 150;
                $pageMmH = $size[1] * 25.4 / 150;

                $pdf->AddPage('P', [$pageMmW, $pageMmH]);
                $pdf->Image($imgPath, 0, 0, $pageMmW, $pageMmH);

                foreach ($template->approvedVariables as $var) {
                    $newValue = $values[$var->name] ?? null;
                    if ($newValue === null || $newValue === '') {
                        continue;
                    }

                    // Use richer occurrence-based positions when available,
                    // fall back to legacy text_positions JSON
                    $positions = $var->resolveOverlayPositions();
                    if (empty($positions)) {
                        continue;
                    }

                    foreach ($positions as $pos) {
                        // Legacy text_positions use 'page' key (1-indexed).
                        // Occurrence-based positions also use 'page' via toOverlayPosition().
                        // Default to 1 (not 0) so single-page docs without an explicit page key still render.
                        $posPage = (int) ($pos['page'] ?? 1);
                        if ($posPage !== $pageNum) {
                            continue;
                        }

                        // Cap font size to a sane range — malformed PDFs can report huge values
                        $fontSize = max(min((float) ($pos['font_size'] ?? 10), 150), 6);

                        // Map percentage coordinates to mm
                        $x = (float) $pos['x_pct'] * $pageMmW;
                        $y = (float) $pos['y_pct'] * $pageMmH;
                        // Minimum width: enough for ~10 chars at this font size (avoids hairline boxes)
                        $w = max((float) $pos['w_pct'] * $pageMmW, $fontSize * 1.8);

                        // Height: use exactly the detected text height (pt → mm).
                        // Capping to font-size-based height prevents the white erasure
                        // rect from being too tall and covering adjacent content above/below.
                        $detectedH  = (float) $pos['h_pct'] * $pageMmH;
                        $fontHeightMm = $fontSize * 25.4 / 72; // points to mm
                        $h = max(min($detectedH, $fontHeightMm * 1.4), $fontHeightMm);

                        // Erase old text (white rect sized to exactly the text line)
                        $pdf->SetFillColor(255, 255, 255);
                        $pdf->Rect($x, $y, $w, $h, 'F');

                        // Resolve text color
                        $hexColor = ltrim($pos['font_color'] ?? '#000000', '#');
                        if (strlen($hexColor) === 3) {
                            $hexColor = $hexColor[0].$hexColor[0].$hexColor[1].$hexColor[1].$hexColor[2].$hexColor[2];
                        }
                        if (strlen($hexColor) === 6 && ctype_xdigit($hexColor)) {
                            $pdf->SetTextColor(
                                hexdec(substr($hexColor, 0, 2)),
                                hexdec(substr($hexColor, 2, 2)),
                                hexdec(substr($hexColor, 4, 2))
                            );
                        } else {
                            $pdf->SetTextColor(0, 0, 0);
                        }

                        // Font weight (bold detection)
                        $fontStyle = (($pos['font_weight'] ?? 'normal') === 'bold') ? 'B' : '';
                        $pdf->SetFont('Helvetica', $fontStyle, $fontSize);
                        $pdf->SetXY($x, $y);

                        // Transliterate UTF-8 to ISO-8859-1 for FPDF
                        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $newValue);
                        $display   = ($converted !== false && $converted !== '') ? $converted : $newValue;

                        // Alignment: C for centered text (signature blocks), L otherwise
                        $align = strtoupper($pos['text_align'] ?? 'L');
                        if (!in_array($align, ['L', 'C', 'R'], true)) {
                            $align = 'L';
                        }

                        // If value is longer than allocated width, shrink font to fit
                        $strWidth = $pdf->GetStringWidth($display);
                        if ($strWidth > $w && $strWidth > 0) {
                            $scaledSize = max($fontSize * ($w / $strWidth), 6);
                            $pdf->SetFont('Helvetica', $fontStyle, $scaledSize);
                        }

                        $pdf->Cell($w, $h, $display, 0, 0, $align);
                    }
                }
            }

            $pdfBytes = $pdf->Output('S');
            $fileName = Str::slug($template->name) . '-' . now()->format('Ymd-His') . '.pdf';
            $path     = 'workspaces/' . $template->workspace_id . '/generated/' . $fileName;
            Storage::disk('documents')->put($path, $pdfBytes);

            return GeneratedDocument::create([
                'workspace_id'    => $template->workspace_id,
                'user_id'         => auth()->id(),
                'template_id'     => $template->id,
                'variable_values' => $values,
                'file_path'       => $path,
                'file_name'       => $fileName,
                'disk'            => 'documents',
                'status'          => 'ready',
            ]);
        } finally {
            foreach (glob($tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }
    }

    // ── HTML/PDF generation (PDF uploads or fallback) ─────────────────

    private function generateFromHtml(Template $template, array $values): GeneratedDocument
    {
        $docText    = $this->extractSourceText($template);
        $filledText = $this->replaceValues($docText, $template, $values);
        $html       = $this->buildHtml($template, $filledText, $values);

        $pdf      = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $pdfBytes = $pdf->output();

        $fileName = Str::slug($template->name) . '-' . now()->format('Ymd-His') . '.pdf';
        $path     = 'workspaces/' . $template->workspace_id . '/generated/' . $fileName;
        Storage::disk('documents')->put($path, $pdfBytes);

        return GeneratedDocument::create([
            'workspace_id'    => $template->workspace_id,
            'user_id'         => auth()->id(),
            'template_id'     => $template->id,
            'variable_values' => $values,
            'file_path'       => $path,
            'file_name'       => $fileName,
            'disk'            => 'documents',
            'status'          => 'ready',
        ]);
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function extractSourceText(Template $template): string
    {
        $doc = $template->uploadedDocument;
        if (!$doc) {
            return '';
        }

        // Use stored extracted text if available (set during AI analysis)
        if (!empty($doc->extracted_text)) {
            return $doc->extracted_text;
        }

        // Fallback: re-extract from DOCX
        if ($doc->isDocx()) {
            $localPath = Storage::disk($doc->disk)->path($doc->path);
            return $this->extractDocxText($localPath);
        }

        return '';
    }

    private function replaceValues(string $text, Template $template, array $values): string
    {
        if (empty($text)) {
            return $text;
        }

        foreach ($template->approvedVariables as $var) {
            $userValue = $values[$var->name] ?? '';
            if ($userValue === '' || empty($var->example_value)) {
                continue;
            }
            $text = str_replace($var->example_value, $userValue, $text);
        }

        return $text;
    }

    private function buildHtml(Template $template, string $filledText, array $values): string
    {
        $variables = $template->approvedVariables;

        // If we have filled text from DOCX, render it as a document
        if (!empty(trim($filledText))) {
            $body = $this->textToHtmlParagraphs($filledText);
        } else {
            // Fallback: render as a clean field-value table (for PDFs or empty extractions)
            $body = $this->buildFieldTable($variables, $values);
        }

        $docTitle    = htmlspecialchars($template->name);
        $rawType     = $template->document_type ? htmlspecialchars($template->document_type) : '';
        $docTypeHtml = $rawType ? "<div class='doc-type'>{$rawType}</div>" : '';
        $date        = now()->format('F j, Y');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11pt; color: #1D2B4F; line-height: 1.6; }
  .page { padding: 50px; }
  .header { border-bottom: 2px solid #2F6BFF; padding-bottom: 16px; margin-bottom: 24px; }
  .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
  .doc-title { font-size: 20pt; font-weight: bold; color: #1D2B4F; }
  .doc-type  { font-size: 10pt; color: #718096; margin-top: 4px; }
  .doc-date  { font-size: 10pt; color: #718096; text-align: right; }
  .content { margin-top: 20px; }
  .content p { margin-bottom: 12px; }
  .field-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
  .field-table th { background: #F4F8FF; color: #1D2B4F; font-weight: bold; padding: 8px 12px; text-align: left; border: 1px solid #E6ECF5; font-size: 9pt; }
  .field-table td { padding: 8px 12px; border: 1px solid #E6ECF5; font-size: 10pt; }
  .field-table tr:nth-child(even) td { background: #F7FAFF; }
  .footer { border-top: 1px solid #E6ECF5; margin-top: 40px; padding-top: 12px; text-align: center; color: #A0AEC0; font-size: 8pt; }
  .badge { display: inline-block; background: #EEF5FF; color: #2F6BFF; padding: 2px 8px; border-radius: 4px; font-size: 9pt; }
</style>
</head>
<body>
<div class="page">
  <div class="header">
    <div class="header-top">
      <div>
        <div class="doc-title">{$docTitle}</div>
        {$docTypeHtml}
      </div>
      <div class="doc-date">Generated: {$date}</div>
    </div>
  </div>
  <div class="content">
    {$body}
  </div>
  <div class="footer">
    Generated by RepetitiveDocs.com &bull; Upload once. Personalize forever.
  </div>
</div>
</body>
</html>
HTML;
    }

    private function textToHtmlParagraphs(string $text): string
    {
        $paragraphs = array_filter(explode("\n", $text), fn($p) => trim($p) !== '');
        $html = '';
        foreach ($paragraphs as $p) {
            $html .= '<p>' . htmlspecialchars(trim($p)) . '</p>';
        }
        return $html;
    }

    private function buildFieldTable($variables, array $values): string
    {
        $rows = '';
        foreach ($variables as $var) {
            $label = htmlspecialchars($var->label);
            $value = htmlspecialchars($values[$var->name] ?? '—');
            $rows .= "<tr><td><strong>{$label}</strong></td><td>{$value}</td></tr>";
        }

        return "<table class='field-table'><tr><th>Field</th><th>Value</th></tr>{$rows}</table>";
    }

    private function extractDocxText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            return '';
        }

        $text = preg_replace('/<w:p[ >]/', "\n", $xml);
        $text = preg_replace('/<w:br[^>]*>/', "\n", $text);
        $text = preg_replace('/<w:tab[^>]*>/', "\t", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
