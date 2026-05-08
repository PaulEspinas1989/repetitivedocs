<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\UploadedDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class VariableDetectionService
{
    public function __construct(private AIProviderService $ai) {}

    /**
     * Analyze an uploaded document, create a Template and its TemplateVariables.
     */
    public function analyze(UploadedDocument $doc): Template
    {
        $doc->update(['status' => 'processing']);

        try {
            $variables = $doc->isPdf()
                ? $this->analyzeWithPdf($doc)
                : $this->analyzeWithText($doc);

            // Store extracted text on the document for use during generation
            if (!empty($variables['document_text'])) {
                $doc->update(['extracted_text' => $variables['document_text']]);
            }

            $variableList = $variables['variables'] ?? $variables;

            $template = Template::create([
                'workspace_id'         => $doc->workspace_id,
                'user_id'              => $doc->user_id,
                'uploaded_document_id' => $doc->id,
                'name'                 => $doc->template_name,
                'document_type'        => $doc->document_type,
                'status'               => 'draft',
            ]);

            // For PDF: extract text positions so generation can overlay values precisely
            $pdfTextElements = [];
            if ($doc->isPdf()) {
                $pdfTextElements = $this->extractPdfTextElements(
                    Storage::disk($doc->disk)->path($doc->path)
                );
            }

            // Use extracted text to count how many times each value appears
            $docText = $variables['document_text'] ?? '';

            foreach ($variableList as $v) {
                $exampleValue  = $v['example_value'] ?? null;
                $textPositions = null;
                $occurrences   = 1;

                if ($doc->isPdf() && !empty($exampleValue) && !empty($pdfTextElements)) {
                    $textPositions = $this->findTextPositions($pdfTextElements, $exampleValue);
                }

                if (!empty($exampleValue) && !empty($docText)) {
                    $occurrences = max(1, substr_count(
                        mb_strtolower($docText),
                        mb_strtolower($exampleValue)
                    ));
                }

                TemplateVariable::create([
                    'template_id'     => $template->id,
                    'workspace_id'    => $doc->workspace_id,
                    'name'            => Str::snake($v['name'] ?? Str::random(8)),
                    'label'           => $v['label'] ?? $v['name'] ?? 'Unknown',
                    'type'            => $v['type'] ?? 'text',
                    'description'     => $v['description'] ?? null,
                    'example_value'   => $exampleValue,
                    'is_required'     => $v['is_required'] ?? true,
                    'sort_order'      => $v['sort_order'] ?? 0,
                    'approval_status' => 'pending',
                    'ai_suggested'    => true,
                    'text_positions'  => $textPositions,
                    'occurrences'     => $occurrences,
                ]);
            }

            // For DOCX: create a template copy with ${var_name} placeholders
            if ($doc->isDocx()) {
                $tplPath = $this->createTemplateCopy($doc, $template->id, $variableList);
                if ($tplPath) {
                    $template->update(['template_docx_path' => $tplPath]);
                }
            }

            $doc->update(['status' => 'processed']);

            return $template;
        } catch (\Throwable $e) {
            $doc->update(['status' => 'failed']);
            throw $e;
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    private function analyzeWithPdf(UploadedDocument $doc): array
    {
        $fileContent = Storage::disk($doc->disk)->get($doc->path);
        $base64      = base64_encode($fileContent);

        $response = $this->ai->messages(
            messages: [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'document',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => 'application/pdf',
                                'data'       => $base64,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $this->buildPrompt($doc->template_name, skipDocumentText: true),
                        ],
                    ],
                ],
            ],
            model:       $this->ai->smartModel(),
            maxTokens:   4096,
            betaHeaders: ['pdfs-2024-09-25'],
        );

        $data = $this->parseResponse($response);

        // Extract text from PDF using pdftotext for generation use
        $pdfText = $this->extractPdfText(Storage::disk($doc->disk)->path($doc->path));
        if (!empty($pdfText)) {
            $data['document_text'] = $pdfText;
        }

        return $data;
    }

    private function extractPdfTextElements(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            return [];
        }

        $base    = sys_get_temp_dir() . '/rdoc_pdfxml_' . uniqid();
        $xmlFile = $base . '.xml';

        exec('pdftohtml -xml ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($base) . ' 2>/dev/null', $out, $code);

        // pdftohtml may generate extra HTML/image files alongside the XML — clean them all up
        $cleanup = glob($base . '*') ?: [];

        if (!file_exists($xmlFile)) {
            foreach ($cleanup as $f) { @unlink($f); }
            return [];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($xmlFile);
        foreach ($cleanup as $f) { @unlink($f); }

        if (!$xml) {
            return [];
        }

        // pdftohtml places fontspec elements on whatever page the font first appears.
        // Pages 2+ reference IDs declared on earlier pages, so we need a global dict.
        // First pass: collect ALL fontspecs across all pages.
        $allFonts = [];
        foreach ($xml->page as $page) {
            foreach ($page->fontspec as $fs) {
                $allFonts[(string) $fs['id']] = [
                    'size'  => (float) $fs['size'],
                    'color' => (string) $fs['color'],
                ];
            }
        }

        // Second pass: extract text elements using the global font map.
        $elements = [];
        foreach ($xml->page as $page) {
            $pageNum    = (int) $page['number'];
            $pageWidth  = (float) ($page['width']  ?: 595);
            $pageHeight = (float) ($page['height'] ?: 842);

            foreach ($page->text as $text) {
                $fontId = (string) $text['font'];
                $font   = $allFonts[$fontId] ?? ['size' => 10, 'color' => '#000000'];

                $elements[] = [
                    'page'        => $pageNum,
                    'top'         => (float) $text['top'],
                    'left'        => (float) $text['left'],
                    'width'       => (float) $text['width'],
                    'height'      => (float) $text['height'],
                    'page_width'  => $pageWidth,
                    'page_height' => $pageHeight,
                    'font_size'   => $font['size'],
                    'font_color'  => $font['color'],
                    'text'        => trim((string) $text),
                ];
            }
        }

        return $elements;
    }

    private function findTextPositions(array $elements, string $search): ?array
    {
        $search = trim($search);

        // Don't match short values (< 5 chars) as substrings — too many false positives
        if (empty($search) || strlen($search) < 5) {
            return null;
        }

        $positions = [];
        $pages = [];
        foreach ($elements as $el) {
            $pages[$el['page']][] = $el;
        }

        foreach ($pages as $pageNum => $pageElements) {
            usort($pageElements, fn($a, $b) => $a['top'] <=> $b['top'] ?: $a['left'] <=> $b['left']);

            // Group into lines (elements within 5px vertically — accommodates mixed font sizes)
            $lines = [];
            foreach ($pageElements as $el) {
                $placed = false;
                foreach ($lines as &$line) {
                    if (abs($el['top'] - $line[0]['top']) <= 5) {
                        $line[] = $el;
                        $placed = true;
                        break;
                    }
                }
                unset($line);
                if (!$placed) {
                    $lines[] = [$el];
                }
            }

            foreach ($lines as $line) {
                usort($line, fn($a, $b) => $a['left'] <=> $b['left']);
                $lineText = trim(implode('', array_column($line, 'text')));

                // Normalize both sides for matching (collapse whitespace, case-insensitive)
                $normalizedLine   = preg_replace('/\s+/', ' ', $lineText);
                $normalizedSearch = preg_replace('/\s+/', ' ', $search);

                $isExactMatch  = mb_strtolower($normalizedLine) === mb_strtolower($normalizedSearch);
                $containsPos   = mb_stripos($normalizedLine, $normalizedSearch);
                $isContainedIn = $containsPos !== false
                                 && (mb_strlen($normalizedSearch) / max(mb_strlen($normalizedLine), 1)) >= 0.6;

                if (!$isExactMatch && !$isContainedIn) {
                    continue;
                }

                $pw = $line[0]['page_width'];
                $ph = $line[0]['page_height'];

                if ($isExactMatch) {
                    $left   = min(array_column($line, 'left'));
                    $right  = max(array_map(fn($e) => $e['left'] + $e['width'], $line));
                    $top    = min(array_column($line, 'top'));
                    $bottom = max(array_map(fn($e) => $e['top'] + $e['height'], $line));
                } else {
                    // Narrow bounding box to the matching sub-portion.
                    // Use $lineText (not normalizedLine) for element offset tracking
                    // so char positions stay consistent with $runningText accumulation.
                    $matchStart  = mb_stripos($lineText, $search);
                    if ($matchStart === false) {
                        // Fall back to normalised search in normalised line
                        $matchStart = $containsPos;
                    }
                    $matchEnd    = $matchStart + mb_strlen($search);
                    $matchLeft   = PHP_INT_MAX;
                    $matchRight  = PHP_INT_MIN;
                    $runningLen  = 0;
                    foreach ($line as $el) {
                        $elStart = $runningLen;
                        $elEnd   = $elStart + mb_strlen($el['text']);
                        if ($elEnd > $matchStart && $elStart < $matchEnd) {
                            $matchLeft  = min($matchLeft,  $el['left']);
                            $matchRight = max($matchRight, $el['left'] + $el['width']);
                        }
                        $runningLen += mb_strlen($el['text']);
                    }
                    $left   = $matchLeft  !== PHP_INT_MAX ? $matchLeft  : min(array_column($line, 'left'));
                    $right  = $matchRight !== PHP_INT_MIN ? $matchRight : max(array_map(fn($e) => $e['left'] + $e['width'], $line));
                    $top    = min(array_column($line, 'top'));
                    $bottom = max(array_map(fn($e) => $e['top'] + $e['height'], $line));
                }

                // Use the largest font size on the line — most likely the value element
                $dominantEl = collect($line)->sortByDesc('font_size')->first();

                $positions[] = [
                    'page'       => $pageNum,
                    'x_pct'      => $left / $pw,
                    'y_pct'      => $top  / $ph,
                    'w_pct'      => ($right - $left) / $pw,
                    'h_pct'      => ($bottom - $top) / $ph,
                    'font_size'  => $dominantEl['font_size'],
                    'font_color' => $dominantEl['font_color'],
                ];

                // Stop after finding the first clear match per page to avoid false duplicates
                break;
            }
        }

        return !empty($positions) ? $positions : null;
    }

    private function extractPdfText(string $pdfPath): string
    {
        if (!file_exists($pdfPath)) {
            return '';
        }

        $output   = [];
        $exitCode = 0;
        exec('pdftotext ' . escapeshellarg($pdfPath) . ' - 2>/dev/null', $output, $exitCode);

        return $exitCode === 0 ? implode("\n", $output) : '';
    }

    private function analyzeWithText(UploadedDocument $doc): array
    {
        $filePath = Storage::disk($doc->disk)->path($doc->path);
        $text     = $this->extractDocxText($filePath);

        if (empty(trim($text))) {
            throw new \RuntimeException('Could not extract text from document.');
        }

        $response = $this->ai->messages(
            messages: [
                [
                    'role'    => 'user',
                    'content' => "Document content:\n\n{$text}\n\n" . $this->buildPrompt($doc->template_name),
                ],
            ],
            model:     $this->ai->fastModel(),
            maxTokens: 4096,
        );

        return $this->parseResponse($response);
    }

    private function createTemplateCopy($doc, int $templateId, array $variableList): ?string
    {
        try {
            $sourcePath = Storage::disk($doc->disk)->path($doc->path);
            $tmpFile    = tempnam(sys_get_temp_dir(), 'rdoc_tpl_') . '.docx';
            copy($sourcePath, $tmpFile);

            $zip = new ZipArchive();
            if ($zip->open($tmpFile) !== true) {
                return null;
            }

            $xmlEntries = ['word/document.xml', 'word/header1.xml', 'word/header2.xml',
                           'word/header3.xml', 'word/footer1.xml', 'word/footer2.xml'];

            foreach ($xmlEntries as $entry) {
                $xml = $zip->getFromName($entry);
                if ($xml === false) {
                    continue;
                }

                foreach ($variableList as $v) {
                    $exampleValue = $v['example_value'] ?? null;
                    $varName      = $v['name'] ?? null;
                    if (empty($exampleValue) || empty($varName)) {
                        continue;
                    }

                    $placeholder = '${' . $varName . '}';

                    // Replace plain occurrence
                    $xml = str_replace($exampleValue, $placeholder, $xml);

                    // Replace XML-escaped occurrence
                    $escaped = htmlspecialchars($exampleValue, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    if ($escaped !== $exampleValue) {
                        $xml = str_replace($escaped, $placeholder, $xml);
                    }
                }

                $zip->addFromString($entry, $xml);
            }

            $zip->close();

            $docxBytes = file_get_contents($tmpFile);
            unlink($tmpFile);

            $path = 'workspaces/' . $doc->workspace_id . '/templates/' . $templateId . '_template.docx';
            Storage::disk('documents')->put($path, $docxBytes);

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractDocxText(string $filePath): string
    {
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

    private function buildPrompt(string $templateName, bool $skipDocumentText = false): string
    {
        $docTextInstruction = $skipDocumentText
            ? ''
            : "1. Extract the full readable text of the document\n";

        $docTextField = $skipDocumentText
            ? ''
            : "  \"document_text\": \"The complete plain text of the document, preserving paragraph breaks with newline characters. Keep all original text exactly as written.\",\n";

        $taskNumber = $skipDocumentText ? '1' : '2';

        return <<<PROMPT
You are an AI document analyzer for RepetitiveDocs, a document personalization platform.

The document is called "{$templateName}". Your job is to find every piece of information that would need to change when this document is reused for a different person, organization, date, or transaction.

{$docTextInstruction}Return ONLY a valid JSON object with this exact structure (no markdown, no explanation):
{
{$docTextField}  "variables": [
    {
      "name": "snake_case_name",
      "label": "Human Readable Label",
      "type": "text|date|number|currency|email|phone|address|select",
      "description": "Brief description of what this field represents",
      "example_value": "The exact text value as it appears in the document",
      "is_required": true,
      "sort_order": 1
    }
  ],
  "summary": {
    "total": 10,
    "categories": { "people": 3, "dates": 2, "amounts": 2, "locations": 1, "contacts": 1, "organizations": 1 }
  }
}

Type guide:
- text: names, titles, positions, IDs, reference numbers, descriptions
- date: any date or time value
- number: quantities, counts, percentages
- currency: monetary amounts with peso/dollar signs
- email: email addresses
- phone: phone or mobile numbers
- address: physical addresses
- select: limited choices (e.g., payment type, civil status)

CRITICAL detection rules — you MUST detect ALL of the following when present:
1. NAMES — full names of people anywhere in the document: recipients, signatories, mayors, officials, clients, employees, owners. Look in ALL sections including headers, body, approval blocks, signature blocks, and footers.
2. POSITIONS/TITLES — job titles and positions next to names (Mayor, Director, President, etc.)
3. ORGANIZATIONS — LGU names, company names, municipality names, barangay names
4. DATES — any date in any format, including document date, validity date, proposal date, signing date
5. AMOUNTS — monetary values, totals, package prices, fees
6. ADDRESSES — office addresses, location references
7. REFERENCE NUMBERS — document numbers, proposal numbers, case numbers
8. APPROVAL SECTIONS — names and positions in approval, signatory, certification, and endorsement blocks are variables — they change per document

Do NOT skip a field just because it is in a header, footer, or approval/signature block. Those sections change every time the document is issued to a different recipient or signed by a different official.

Do NOT include: fixed legal boilerplate text, static instruction text, column headers in tables, or document titles that never change.

example_value MUST be the COMPLETE exact text as it appears (e.g. "Hon. Mayor Charizze Marie Escalona" not "Escalona", "May 6, 2026" not "May").

Use snake_case names. Keep labels 2–4 words. sort_order = top-to-bottom reading order.

Return ONLY the JSON object — no explanation, no markdown fences.
PROMPT;
    }

    private function parseResponse(array $response): array
    {
        $data = $this->ai->extractJson($response);

        if (!$data || !isset($data['variables']) || !is_array($data['variables'])) {
            $raw = $this->ai->extractText($response);
            \Illuminate\Support\Facades\Log::error('VariableDetection: unexpected AI response', [
                'raw' => substr($raw, 0, 1000),
            ]);
            throw new \RuntimeException('AI returned an unexpected response format.');
        }

        // Return full data so document_text can be stored
        return $data;
    }
}
