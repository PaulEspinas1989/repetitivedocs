<?php

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateVariable;
use App\Models\VariableOccurrence;
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

            // For PDF: extract text positions for overlay rendering
            $pdfTextElements = [];
            if ($doc->isPdf()) {
                $pdfTextElements = $this->extractPdfTextElements(
                    Storage::disk($doc->disk)->path($doc->path)
                );
            }

            $docText = $variables['document_text'] ?? '';

            foreach ($variableList as $v) {
                $exampleValue = $v['example_value'] ?? null;

                // Count how many times the value appears in the document text
                $occurrences = 1;
                if (!empty($exampleValue) && !empty($docText)) {
                    $count = preg_match_all(
                        '/' . preg_quote(mb_strtolower($exampleValue), '/') . '/u',
                        mb_strtolower($docText)
                    );
                    $occurrences = max(1, (int) $count);
                }

                // Find ALL text positions for this value across ALL pages
                $allPositions = [];
                if ($doc->isPdf() && !empty($exampleValue) && !empty($pdfTextElements)) {
                    $allPositions = $this->findAllTextPositions($pdfTextElements, $exampleValue);
                }

                // The legacy text_positions field keeps the first position for backward compat
                $textPositions = !empty($allPositions) ? $allPositions : null;

                // Merge AI-returned occurrence metadata if present
                $aiOccurrences = $v['occurrences'] ?? [];

                $variable = TemplateVariable::create([
                    'template_id'         => $template->id,
                    'workspace_id'        => $doc->workspace_id,
                    'name'                => Str::snake($v['name'] ?? Str::random(8)),
                    'label'               => $v['label'] ?? $v['name'] ?? 'Unknown',
                    'type'                => $v['type'] ?? 'text',
                    'description'         => $v['description'] ?? null,
                    'example_value'       => $exampleValue,
                    'is_required'         => $v['is_required'] ?? true,
                    'sort_order'          => $v['sort_order'] ?? 0,
                    'approval_status'     => 'pending',
                    'ai_suggested'        => true,
                    'text_positions'      => $textPositions,
                    'occurrences'         => $occurrences,
                    'semantic_type'       => $v['semantic_type'] ?? null,
                    'entity_role'         => $v['entity_role'] ?? null,
                    'grouping_confidence' => isset($v['confidence_score'])
                                            ? (int) round($v['confidence_score'] * 100)
                                            : null,
                    'grouping_reason'     => $v['grouping_reason'] ?? null,
                ]);

                // Create VariableOccurrence records — one per detected position
                $this->createOccurrenceRecords(
                    $variable,
                    $template->id,
                    $doc->workspace_id,
                    $allPositions,
                    $aiOccurrences,
                    $exampleValue
                );
            }

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

    private function createOccurrenceRecords(
        TemplateVariable $variable,
        int $templateId,
        int $workspaceId,
        array $pdfPositions,
        array $aiOccurrences,
        ?string $exampleValue
    ): void {
        // Index AI occurrence metadata by page so we can match each PDF position to its AI metadata.
        // Using references on $aiByPage so array_shift() correctly advances the pointer for each page.
        $aiByPage = [];
        foreach ($aiOccurrences as $occ) {
            $page = $occ['page_number'] ?? null;
            $aiByPage[$page ?? 'any'][] = $occ;
        }

        if (!empty($pdfPositions)) {
            foreach ($pdfPositions as $pos) {
                $page = $pos['page'] ?? null;

                // Pop the next AI occurrence for this page (or from the 'any' pool).
                // Use array_shift directly on $aiByPage entries so each position
                // consumes a different AI occurrence instead of all getting the same first one.
                $ai = [];
                if (!empty($aiByPage[$page])) {
                    $ai = array_shift($aiByPage[$page]);
                } elseif (!empty($aiByPage['any'])) {
                    $ai = array_shift($aiByPage['any']);
                }

                VariableOccurrence::create([
                    'template_variable_id' => $variable->id,
                    'template_id'          => $templateId,
                    'workspace_id'         => $workspaceId,
                    'page_number'          => $page,
                    'original_text'        => $ai['original_text'] ?? $exampleValue,
                    'normalized_text'      => $ai['normalized_value'] ?? $this->normalizeText($exampleValue ?? ''),
                    'prefix_text'          => $ai['prefix_text'] ?? null,
                    'suffix_text'          => $ai['suffix_text'] ?? null,
                    'context_before'       => $ai['context_before'] ?? null,
                    'context_after'        => $ai['context_after'] ?? null,
                    'semantic_context'     => $ai['semantic_context'] ?? null,
                    'replacement_strategy' => $ai['recommended_replacement_strategy'] ?? 'replace_exact_text_preserve_style',
                    'confidence_pct'       => isset($ai['confidence_score'])
                                             ? (int) round($ai['confidence_score'] * 100)
                                             : 100,
                    'status'               => 'active',
                    'bounding_box'         => [
                        'x_pct' => $pos['x_pct'],
                        'y_pct' => $pos['y_pct'],
                        'w_pct' => $pos['w_pct'],
                        'h_pct' => $pos['h_pct'],
                    ],
                    'style_snapshot'       => [
                        'font_size'   => $pos['font_size']   ?? 10,
                        'font_color'  => $pos['font_color']  ?? '#000000',
                        'font_family' => $pos['font_family'] ?? 'Helvetica',
                        'font_weight' => $pos['font_weight'] ?? 'normal',
                        'text_align'  => $pos['text_align']  ?? 'L',
                    ],
                ]);
            }
        } elseif (!empty($aiOccurrences)) {
            // PDF positions not available — store AI metadata only (DOCX or no pdftohtml)
            foreach ($aiOccurrences as $occ) {
                VariableOccurrence::create([
                    'template_variable_id' => $variable->id,
                    'template_id'          => $templateId,
                    'workspace_id'         => $workspaceId,
                    'page_number'          => $occ['page_number'] ?? null,
                    'original_text'        => $occ['original_text'] ?? $exampleValue,
                    'normalized_text'      => $occ['normalized_value'] ?? $this->normalizeText($exampleValue ?? ''),
                    'prefix_text'          => $occ['prefix_text'] ?? null,
                    'suffix_text'          => $occ['suffix_text'] ?? null,
                    'context_before'       => $occ['context_before'] ?? null,
                    'context_after'        => $occ['context_after'] ?? null,
                    'semantic_context'     => $occ['semantic_context'] ?? null,
                    'replacement_strategy' => $occ['recommended_replacement_strategy'] ?? 'replace_exact_text_preserve_style',
                    'confidence_pct'       => isset($occ['confidence_score'])
                                             ? (int) round($occ['confidence_score'] * 100)
                                             : 100,
                    'status'               => 'active',
                ]);
            }
        } elseif (!empty($exampleValue)) {
            // Minimum: one placeholder occurrence record so the UI can show it
            VariableOccurrence::create([
                'template_variable_id' => $variable->id,
                'template_id'          => $templateId,
                'workspace_id'         => $workspaceId,
                'original_text'        => $exampleValue,
                'normalized_text'      => $this->normalizeText($exampleValue),
                'replacement_strategy' => 'replace_exact_text_preserve_style',
                'confidence_pct'       => 80,
                'status'               => 'active',
            ]);
        }
    }

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
            maxTokens:   6000,
            betaHeaders: ['pdfs-2024-09-25'],
        );

        $data = $this->parseResponse($response);

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

        // First pass: collect all fontspec definitions globally across all pages
        $allFonts = [];
        foreach ($xml->page as $page) {
            foreach ($page->fontspec as $fs) {
                $allFonts[(string) $fs['id']] = [
                    'size'   => (float) $fs['size'],
                    'color'  => (string) $fs['color'],
                    'family' => (string) ($fs['family'] ?? ''),
                    'weight' => str_contains(strtolower((string) ($fs['family'] ?? '')), 'bold') ? 'bold' : 'normal',
                ];
            }
        }

        // Second pass: extract text elements with full font info
        $elements = [];
        foreach ($xml->page as $page) {
            $pageNum    = (int) $page['number'];
            $pageWidth  = (float) ($page['width']  ?: 595);
            $pageHeight = (float) ($page['height'] ?: 842);

            foreach ($page->text as $text) {
                $fontId = (string) $text['font'];
                $font   = $allFonts[$fontId] ?? ['size' => 10, 'color' => '#000000', 'family' => '', 'weight' => 'normal'];

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
                    'font_family' => $font['family'],
                    'font_weight' => $font['weight'],
                    'text'        => trim((string) $text),
                ];
            }
        }

        return $elements;
    }

    /**
     * Find ALL positions of the search value across all pages.
     * Previously only captured the first match per page — now returns every match.
     */
    private function findAllTextPositions(array $elements, string $search): array
    {
        $search = trim($search);

        if (empty($search) || mb_strlen($search) < 5) {
            return [];
        }

        $positions = [];
        $pages = [];
        foreach ($elements as $el) {
            $pages[$el['page']][] = $el;
        }

        foreach ($pages as $pageNum => $pageElements) {
            usort($pageElements, fn($a, $b) => $a['top'] <=> $b['top'] ?: $a['left'] <=> $b['left']);

            // Group into lines (within 5px vertically)
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
                    $matchStart = mb_stripos($lineText, $search);
                    if ($matchStart === false) {
                        $matchStart = $containsPos;
                    }
                    $matchEnd   = $matchStart + mb_strlen($search);
                    $matchLeft  = PHP_INT_MAX;
                    $matchRight = PHP_INT_MIN;
                    $runningLen = 0;
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

                $dominantEl = collect($line)->sortByDesc('font_size')->first();

                // Detect text alignment from horizontal position on the page
                $centerOfText = ($left + $right) / 2;
                $textAlign = 'L';
                if ($centerOfText >= ($pw * 0.38) && $centerOfText <= ($pw * 0.62)) {
                    $textAlign = 'C';
                } elseif ($right >= ($pw * 0.85)) {
                    $textAlign = 'R';
                }

                $positions[] = [
                    'page'        => $pageNum,
                    'x_pct'      => $left / $pw,
                    'y_pct'      => $top  / $ph,
                    'w_pct'      => ($right - $left) / $pw,
                    'h_pct'      => ($bottom - $top) / $ph,
                    'font_size'  => $dominantEl['font_size'],
                    'font_color' => $dominantEl['font_color'],
                    'font_family' => $dominantEl['font_family'] ?? '',
                    'font_weight' => $dominantEl['font_weight'] ?? 'normal',
                    'text_align' => $textAlign,
                ];
                // NOTE: no break — capture ALL matching lines on this page
            }
        }

        return $positions;
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
            maxTokens: 6000,
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
                    $xml = str_replace($exampleValue, $placeholder, $xml);

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

    /**
     * Normalize a text value for grouping comparison.
     * Strips honorifics, lowercases, collapses whitespace.
     */
    private function normalizeText(string $text): string
    {
        // Strip common honorific prefixes (Filipino/English contexts)
        $honorifics = [
            '/^(Hon\.|Honorable|Mayor|Gov\.|Governor|Atty\.|Dr\.|Engr\.|Mr\.|Mrs\.|Ms\.|City Mayor|Municipal Mayor|Punong Bayan|Punong Lungsod)\s+/i',
        ];
        $normalized = $text;
        foreach ($honorifics as $pattern) {
            $normalized = preg_replace($pattern, '', $normalized);
        }

        return mb_strtolower(preg_replace('/\s+/', ' ', trim($normalized)));
    }

    private function buildPrompt(string $templateName, bool $skipDocumentText = false): string
    {
        $docTextInstruction = $skipDocumentText
            ? ''
            : "1. Extract the full readable text of the document\n";

        $docTextField = $skipDocumentText
            ? ''
            : "  \"document_text\": \"The complete plain text of the document, preserving paragraph breaks with newline characters. Keep all original text exactly as written.\",\n";

        return <<<PROMPT
You are an AI document analyzer for RepetitiveDocs, a document personalization platform.

The document is called "{$templateName}". Your job is to:
{$docTextInstruction}Find every piece of information that changes when this document is reused for a different person, date, organization, or transaction.

CRITICAL: When the SAME real-world value appears multiple times (e.g., a mayor's name appears in the header, body, approval section, and signature block), create ONE variable with multiple occurrences — do NOT create separate variables for the same logical field.

Return ONLY a valid JSON object with this exact structure (no markdown, no explanation):
{
{$docTextField}  "variables": [
    {
      "name": "snake_case_name",
      "label": "Human Readable Label",
      "type": "text|date|number|currency|email|phone|address|select",
      "description": "Brief description of what this field represents",
      "example_value": "The canonical value (clean, without honorific prefix, e.g. 'Juan Dela Cruz' not 'HON. JUAN DELA CRUZ')",
      "is_required": true,
      "sort_order": 1,
      "semantic_type": "person_name|org_name|date|currency|reference_number|address|phone|email|text",
      "entity_role": "mayor_signatory|recipient|company|employee|date_signed|amount|reference|location|other",
      "confidence_score": 0.95,
      "grouping_reason": "Brief reason why multiple placements were grouped (if occurrences > 1)",
      "occurrences": [
        {
          "original_text": "The EXACT text as it appears in this placement (e.g. 'HON. JUAN DELA CRUZ')",
          "normalized_value": "Cleaned version without honorifics (e.g. 'Juan Dela Cruz')",
          "prefix_text": "HON.",
          "suffix_text": "",
          "page_number": 1,
          "context_before": "Text directly before this value",
          "context_after": "Text directly after this value (e.g. 'Municipal Mayor')",
          "semantic_context": "signature_block|labeled_field|header|footer|body|approval_block",
          "recommended_replacement_strategy": "replace_exact_text_preserve_style|replace_value_preserve_prefix|replace_signature_block_name"
        }
      ]
    }
  ],
  "summary": {
    "total": 10,
    "categories": { "people": 3, "dates": 2, "amounts": 2, "locations": 1, "contacts": 1, "organizations": 1 }
  }
}

GROUPING RULES — apply these to determine when one variable should cover multiple placements:
- If the same PERSON appears as "Juan Dela Cruz", "HON. JUAN DELA CRUZ", "Mayor Juan Dela Cruz", "Name of Mayor: Juan Dela Cruz" — these are ONE variable: Mayor Name
- If the same ORG appears as "City of Masbate", "Masbate City" — these are ONE variable
- If the same DATE appears in multiple formats — these are ONE variable
- Group by REAL-WORLD IDENTITY, not by exact text match

MANDATORY SIGNATORY AND MAYOR DETECTION:
You MUST detect and create variables for:
1. Mayor / City Mayor / Municipal Mayor / Local Chief Executive names
2. Any name appearing ABOVE a title like "Municipal Mayor", "City Mayor", "Governor", "Vice Mayor"
3. Any name appearing after "Approved by:", "Certified by:", "Signed by:", "Noted by:", "Prepared by:"
4. Any name in ALL CAPS that is near a title (signature block pattern)
5. Names preceded by "Hon." or "Honorable"
6. Names in signature/approval/certification blocks anywhere in the document
7. Barangay Captain, Governor, Department Head names

For mayor/signatory names:
- example_value should be the CLEAN NAME ONLY (e.g., "Olga T. Kho") — strip "HON.", "Mayor", etc.
- Each placement where the name appears differently is one occurrence
- entity_role = "mayor_signatory" or appropriate role
- semantic_type = "person_name"

WHAT TO DETECT in variables array:
- Full names of recipients, signatories, officials, clients, employees
- Job titles and positions that change per document
- Organization/LGU/company names
- All dates (document date, validity date, signing date)
- Monetary amounts and fees
- Reference/case/document numbers
- Physical addresses
- Approval/signatory blocks (these ALWAYS change when re-using the document)

Do NOT include: fixed legal boilerplate, static instruction text, table column headers, document titles that never change.

For occurrences array: include ONE entry per placement where the value appears in the document.
example_value = the canonical clean value (strip honorifics for comparison).

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

        return $data;
    }
}
