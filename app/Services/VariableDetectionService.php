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
    public function __construct(
        private AIProviderService $ai,
        private DateFormatterService $dateFormatter,
    ) {}

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

                $textPositions = !empty($allPositions) ? $allPositions : null;

                // Guard: AI may return "occurrences": 3 (integer) instead of an array
                $aiOccurrences = is_array($v['occurrences'] ?? null) ? $v['occurrences'] : [];

                // Confidence below 0.7 → flag for needs_review
                $confidenceScore = (float) ($v['confidence_score'] ?? 1.0);
                $needsReview     = $confidenceScore < 0.70;

                $varType = $v['type'] ?? 'text';

                // For date fields, detect the format from the AI-extracted example value
                // so the generation pipeline can reformat ISO date input to match the original.
                $dateFormat = null;
                if ($varType === 'date' && !empty($exampleValue)) {
                    $dateFormat = $this->dateFormatter->detectFormat($exampleValue);
                }

                $variable = TemplateVariable::create([
                    'template_id'         => $template->id,
                    'workspace_id'        => $doc->workspace_id,
                    'name'                => Str::snake($v['name'] ?? Str::random(8)),
                    'label'               => $v['label'] ?? $v['name'] ?? 'Unknown',
                    'type'                => $varType,
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
                    'grouping_confidence' => (int) round($confidenceScore * 100),
                    'grouping_reason'     => $v['grouping_reason'] ?? null,
                    'needs_review'        => $needsReview,
                    'date_format'         => $dateFormat,
                ]);

                $this->createOccurrenceRecords(
                    $variable, $template->id, $doc->workspace_id,
                    $allPositions, $aiOccurrences, $exampleValue
                );
            }

            // Store AI's "needs_review" uncertain candidates as additional template variables
            $reviewCandidates = is_array($variables['needs_review_candidates'] ?? null)
                ? $variables['needs_review_candidates']
                : [];

            $sortOrder = count($variableList) + 1;
            foreach ($reviewCandidates as $candidate) {
                $origText = $candidate['original_text'] ?? null;
                if (empty($origText)) {
                    continue;
                }

                TemplateVariable::create([
                    'template_id'     => $template->id,
                    'workspace_id'    => $doc->workspace_id,
                    'name'            => Str::snake($candidate['suggested_display_name'] ?? 'field_' . $sortOrder),
                    'label'           => $candidate['suggested_display_name'] ?? 'Unknown Field',
                    'type'            => 'text',
                    'description'     => $candidate['reason'] ?? null,
                    'example_value'   => $origText,
                    'is_required'     => false,
                    'sort_order'      => $sortOrder++,
                    'approval_status' => 'pending',
                    'ai_suggested'    => true,
                    'needs_review'    => true,
                    'needs_review_reason' => $candidate['reason'] ?? null,
                    'semantic_type'   => $candidate['suggested_semantic_type'] ?? null,
                    'grouping_confidence' => $candidate['confidence'] === 'low' ? 40 : 60,
                ]);
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

                $originalText  = $ai['original_text'] ?? $exampleValue ?? '';
                $casingPattern = $pos['casing_pattern'] ?? $this->detectCasingPattern($originalText);

                VariableOccurrence::create([
                    'template_variable_id' => $variable->id,
                    'template_id'          => $templateId,
                    'workspace_id'         => $workspaceId,
                    'page_number'          => $page,
                    'original_text'        => $originalText,
                    'normalized_text'      => $ai['normalized_value'] ?? $this->normalizeText($exampleValue ?? ''),
                    'prefix_text'          => $ai['prefix_text']  ?? null,
                    'suffix_text'          => $ai['suffix_text']  ?? null,
                    'context_before'       => $ai['context_before'] ?? null,
                    'context_after'        => $ai['context_after']  ?? null,
                    'semantic_context'     => $ai['semantic_context'] ?? null,
                    'replacement_strategy' => $ai['recommended_replacement_strategy']
                                             ?? 'replace_exact_text_preserve_style',
                    'confidence_pct'       => isset($ai['confidence_score'])
                                             ? (int) round($ai['confidence_score'] * 100) : 100,
                    'status'               => 'active',
                    // New detection metadata
                    'source_area'          => $ai['source_area']   ?? $pos['source_area']  ?? 'body',
                    'nearby_label'         => $ai['nearby_label']  ?? null,
                    'casing_pattern'       => $casingPattern,
                    'detection_source'     => 'pdf_position',
                    'bounding_box'         => [
                        'x_pct' => $pos['x_pct'],
                        'y_pct' => $pos['y_pct'],
                        'w_pct' => $pos['w_pct'],
                        'h_pct' => $pos['h_pct'],
                    ],
                    'style_snapshot'       => [
                        'font_size'   => $pos['font_size']   ?? 10,
                        'font_color'  => $pos['font_color']  ?? '#000000',
                        'font_family' => $pos['font_family'] ?? '',
                        'font_weight' => $pos['font_weight'] ?? 'normal',
                        'text_align'  => $pos['text_align']  ?? 'L',
                    ],
                ]);
            }
        } elseif (!empty($aiOccurrences)) {
            // Vision-based path: AI occurrences carry bounding_box directly.
            // Use them — no pdftohtml coordinate lookup needed.
            foreach ($aiOccurrences as $occ) {
                $originalText  = $occ['original_text'] ?? $exampleValue ?? '';
                $casingPattern = $occ['casing_pattern'] ?? $this->detectCasingPattern($originalText);

                // Extract Vision-provided bounding box (x_pct, y_pct, w_pct, h_pct)
                $bbox = null;
                if (
                    isset($occ['bounding_box']['x_pct']) &&
                    isset($occ['bounding_box']['y_pct']) &&
                    isset($occ['bounding_box']['w_pct']) &&
                    isset($occ['bounding_box']['h_pct'])
                ) {
                    $bbox = [
                        'x_pct' => (float) $occ['bounding_box']['x_pct'],
                        'y_pct' => (float) $occ['bounding_box']['y_pct'],
                        'w_pct' => (float) $occ['bounding_box']['w_pct'],
                        'h_pct' => (float) $occ['bounding_box']['h_pct'],
                    ];
                }

                $isBold = !empty($occ['is_bold']);
                $align  = strtoupper($occ['text_align'] ?? 'L');
                if (!in_array($align, ['L', 'C', 'R'], true)) {
                    $align = 'L';
                }

                VariableOccurrence::create([
                    'template_variable_id' => $variable->id,
                    'template_id'          => $templateId,
                    'workspace_id'         => $workspaceId,
                    'page_number'          => $occ['page_number'] ?? null,
                    'original_text'        => $originalText,
                    'normalized_text'      => $occ['normalized_value'] ?? $this->normalizeText($exampleValue ?? ''),
                    'prefix_text'          => $occ['prefix_text']  ?? null,
                    'suffix_text'          => $occ['suffix_text']  ?? null,
                    'context_before'       => $occ['context_before'] ?? null,
                    'context_after'        => $occ['context_after']  ?? null,
                    'semantic_context'     => $occ['semantic_context'] ?? null,
                    'replacement_strategy' => $occ['recommended_replacement_strategy']
                                             ?? 'replace_exact_text_preserve_style',
                    'confidence_pct'       => isset($occ['confidence_score'])
                                             ? (int) round($occ['confidence_score'] * 100) : 100,
                    'status'               => 'active',
                    'source_area'          => $occ['source_area']  ?? 'body',
                    'nearby_label'         => $occ['nearby_label'] ?? null,
                    'casing_pattern'       => $casingPattern,
                    'detection_source'     => 'vision',
                    'bounding_box'         => $bbox,
                    'style_snapshot'       => $bbox ? [
                        'font_size'   => 10,
                        'font_color'  => '#000000',
                        'font_family' => '',
                        'font_weight' => $isBold ? 'bold' : 'normal',
                        'text_align'  => $align,
                    ] : null,
                ]);
            }
        } elseif (!empty($exampleValue)) {
            VariableOccurrence::create([
                'template_variable_id' => $variable->id,
                'template_id'          => $templateId,
                'workspace_id'         => $workspaceId,
                'original_text'        => $exampleValue,
                'normalized_text'      => $this->normalizeText($exampleValue),
                'replacement_strategy' => 'replace_exact_text_preserve_style',
                'confidence_pct'       => 80,
                'status'               => 'active',
                'casing_pattern'       => $this->detectCasingPattern($exampleValue),
                'detection_source'     => 'fallback',
            ]);
        }
    }

    /**
     * Detect the casing pattern of a text value.
     * Used to preserve casing when rendering replacement text.
     */
    private function detectCasingPattern(string $text): string
    {
        $text = trim($text);
        if (empty($text) || !preg_match('/[a-zA-Z]/', $text)) {
            return 'mixed';
        }

        // All uppercase — no lowercase letters
        if ($text === mb_strtoupper($text) && preg_match('/[A-Z]/', $text)) {
            return 'uppercase';
        }

        // All lowercase — no uppercase letters
        if ($text === mb_strtolower($text) && preg_match('/[a-z]/', $text)) {
            return 'lowercase';
        }

        // Title case — every word (length > 1) starts with uppercase
        $words  = preg_split('/\s+/', $text);
        $isTitle = true;
        foreach ($words as $word) {
            $letters = preg_replace('/[^a-zA-Z]/', '', $word);
            if (mb_strlen($letters) > 0 && mb_strtoupper($letters[0]) !== $letters[0]) {
                $isTitle = false;
                break;
            }
        }
        if ($isTitle && count($words) > 0) {
            return 'titlecase';
        }

        return 'mixed';
    }

    /**
     * Vision-based PDF analysis.
     *
     * Why: The old approach sent the PDF binary (PDF beta header) and separately
     * ran pdftohtml to find text coordinates — two independent systems that
     * consistently produce coordinate mismatches, so generated values appear in
     * the wrong position or not at all.
     *
     * New approach:
     *   1. Rasterize PDF → PNG images (same tool/DPI used at generation time)
     *   2. Send those images to Claude Vision in one call
     *   3. Claude sees the SAME visual layout and returns bounding boxes as
     *      fractions of the image — the exact coordinate system generation uses
     *   4. Zero coordinate mismatch possible
     */
    private function analyzeWithPdf(UploadedDocument $doc): array
    {
        $pdfPath = Storage::disk($doc->disk)->path($doc->path);
        $tmpDir  = sys_get_temp_dir() . '/rdoc_vision_' . uniqid();
        mkdir($tmpDir, 0777, true);

        try {
            // Rasterize at 100 DPI — sufficient for Vision to read text clearly,
            // cheaper on tokens than 150 DPI. Generation still uses 150 DPI for
            // output quality; bounding box percentages are DPI-independent.
            exec('pdftoppm -r 100 -png ' . escapeshellarg($pdfPath) . ' ' . escapeshellarg($tmpDir . '/page') . ' 2>/dev/null');

            $images = glob($tmpDir . '/page-*.png') ?: glob($tmpDir . '/page*.png') ?: [];
            natsort($images);
            $images = array_values($images);

            if (empty($images)) {
                // pdftoppm not available — fall through to text analysis
                return $this->analyzeWithText($doc);
            }

            // Build Vision API message: page labels + images + prompt
            $content = [];
            foreach ($images as $idx => $imgPath) {
                $imgBytes = file_get_contents($imgPath);
                if (!$imgBytes) {
                    continue;
                }
                $content[] = ['type' => 'text', 'text' => 'PAGE ' . ($idx + 1) . ':'];
                $content[] = [
                    'type'   => 'image',
                    'source' => [
                        'type'       => 'base64',
                        'media_type' => 'image/png',
                        'data'       => base64_encode($imgBytes),
                    ],
                ];
            }

            if (count($content) === 0) {
                return $this->analyzeWithText($doc);
            }

            $content[] = ['type' => 'text', 'text' => $this->buildVisionPrompt($doc->template_name)];

            $response = $this->ai->messages(
                messages: [['role' => 'user', 'content' => $content]],
                model:    $this->ai->smartModel(),
                maxTokens: 8192,
            );

            $data = $this->parseResponse($response);

            // Also extract full text for search/context storage
            $pdfText = $this->extractPdfText($pdfPath);
            if (!empty($pdfText)) {
                $data['document_text'] = $pdfText;
            }

            return $data;

        } finally {
            foreach (glob($tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($tmpDir);
        }
    }

    private function buildVisionPrompt(string $templateName): string
    {
        return <<<PROMPT
You are analyzing the pages of a Philippine government/business document template called "{$templateName}".

TASK: Find every text value that must change when this document is personalized for a different recipient, date, amount, or transaction. Return ALL occurrences of each variable — even if the same name appears 7 times, list all 7 with separate bounding boxes.

BOUNDING BOXES: For every occurrence, measure its position as a fraction of that PAGE IMAGE's pixel dimensions:
- x_pct: left edge  (0.0 = leftmost, 1.0 = rightmost)
- y_pct: top edge   (0.0 = topmost,  1.0 = bottommost)
- w_pct: width      (fraction of page width)
- h_pct: line height (fraction of page height, typically 0.02–0.05)

RULES:
- Group all occurrences of the same real-world value (e.g. a mayor's name) under ONE variable
- Include every placement: headers, body text, tables, signature blocks, footers
- example_value = the exact text as it appears (e.g. "OLGA T. KHO" not "Mayor Olga Kho")
- Never omit uncertain items — put them in needs_review_candidates

Return ONLY valid JSON (no markdown):
{
  "variables": [
    {
      "name": "snake_case_key",
      "label": "2-4 word label",
      "type": "text|date|number|currency|email|phone|address",
      "description": "brief description",
      "example_value": "exact value in document",
      "is_required": true,
      "sort_order": 1,
      "semantic_type": "person_name|org_name|date|currency|reference_number|address|text",
      "entity_role": "mayor_signatory|recipient|company|date_signed|amount|reference|other",
      "confidence_score": 0.95,
      "occurrences": [
        {
          "page_number": 1,
          "original_text": "EXACT TEXT AS SHOWN",
          "bounding_box": {"x_pct": 0.10, "y_pct": 0.18, "w_pct": 0.40, "h_pct": 0.025},
          "prefix_text": "HON.",
          "casing_pattern": "uppercase|titlecase|lowercase|mixed",
          "source_area": "body|header|footer|table|signature_block",
          "is_bold": true,
          "text_align": "L|C|R"
        }
      ]
    }
  ],
  "needs_review_candidates": [
    {"original_text": "uncertain text", "suggested_display_name": "Field Name", "reason": "why uncertain", "confidence": "medium|low"}
  ]
}
PROMPT;
    }

    public function extractPdfTextElements(string $pdfPath): array
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

                $rawText = trim((string) $text);
                $topRaw  = (float) $text['top'];

                // Heuristic: top 10% of page = header, bottom 10% = footer
                $topPct     = $pageHeight > 0 ? ($topRaw / $pageHeight) : 0.5;
                $sourceArea = 'body';
                if ($topPct < 0.10) {
                    $sourceArea = 'header';
                } elseif ($topPct > 0.90) {
                    $sourceArea = 'footer';
                }

                $elements[] = [
                    'page'           => $pageNum,
                    'top'            => $topRaw,
                    'left'           => (float) $text['left'],
                    'width'          => (float) $text['width'],
                    'height'         => (float) $text['height'],
                    'page_width'     => $pageWidth,
                    'page_height'    => $pageHeight,
                    'font_size'      => $font['size'],
                    'font_color'     => $font['color'],
                    'font_family'    => $font['family'],
                    'font_weight'    => $font['weight'],
                    'text'           => $rawText,
                    'source_area'    => $sourceArea,
                    'casing_pattern' => $this->detectCasingPattern($rawText),
                ];
            }
        }

        return $elements;
    }

    /**
     * Find ALL positions of the search value across all pages.
     * Previously only captured the first match per page — now returns every match.
     */
    public function findAllTextPositions(array $elements, string $search): array
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

                $pw = (float) $line[0]['page_width'];
                $ph = (float) $line[0]['page_height'];

                // Skip if page dimensions are missing or zero (malformed PDF)
                if ($pw <= 0 || $ph <= 0) {
                    continue;
                }

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

                // Safety: first() returns null only for empty collections; $line always has ≥1 element
                // (initialized with [$el] in the grouping loop above), but guard anyway.
                $dominantEl = collect($line)->sortByDesc('font_size')->first() ?? $line[0];

                // Detect text alignment from horizontal position on the page
                $centerOfText = ($left + $right) / 2;
                $textAlign = 'L';
                if ($centerOfText >= ($pw * 0.38) && $centerOfText <= ($pw * 0.62)) {
                    $textAlign = 'C';
                } elseif ($right >= ($pw * 0.85)) {
                    $textAlign = 'R';
                }

                // Casing pattern from the original matched text (dominant element or full line)
                $matchedText   = $isExactMatch ? $lineText : (string) ($dominantEl['text'] ?? $lineText);
                $casingPattern = $this->detectCasingPattern($matchedText);

                // Source area from the dominant element (header/footer/body detected during extraction)
                $sourceArea = $dominantEl['source_area'] ?? 'body';

                // Clamp all percentage values to [0, 1] so malformed PDFs with
                // text elements outside page bounds don't push overlay off-page.
                $positions[] = [
                    'page'           => $pageNum,
                    'x_pct'         => max(0, min(1, $left / $pw)),
                    'y_pct'         => max(0, min(1, $top  / $ph)),
                    'w_pct'         => max(0, min(1, ($right - $left) / $pw)),
                    'h_pct'         => max(0, min(1, ($bottom - $top) / $ph)),
                    'font_size'      => $dominantEl['font_size'],
                    'font_color'     => $dominantEl['font_color'],
                    'font_family'    => $dominantEl['font_family'] ?? '',
                    'font_weight'    => $dominantEl['font_weight'] ?? 'normal',
                    'text_align'     => $textAlign,
                    'source_area'    => $sourceArea,
                    'casing_pattern' => $casingPattern,
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
            maxTokens: 8192,
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
            : "  \"document_text\": \"The complete plain text of the document, preserving paragraph breaks. Keep all original text exactly as written.\",\n";

        return <<<PROMPT
You are an AI document analyzer for RepetitiveDocs. Find every value a user must change when reusing "{$templateName}" for a different person, date, or transaction.
{$docTextInstruction}
Rules: (1) Never omit values just because they aren't in brackets. (2) Check headers, footers, tables, and signature blocks. (3) When the same real-world value appears multiple times, create ONE variable with multiple occurrences — not separate variables. (4) Return uncertain items in needs_review_candidates, never drop them silently.

Return ONLY valid JSON (no markdown):
{
{$docTextField}  "variables": [
    {
      "name": "snake_case_key",
      "label": "2-4 word label",
      "type": "text|date|number|currency|email|phone|address|select",
      "description": "brief description",
      "example_value": "clean value, NO honorific prefix",
      "is_required": true,
      "sort_order": 1,
      "semantic_type": "person_name|org_name|date|currency|reference_number|address|phone|email|text",
      "entity_role": "mayor_signatory|recipient|company|employee|date_signed|amount|reference|location|other",
      "confidence_score": 0.95,
      "grouping_reason": "why occurrences are grouped",
      "occurrences": [
        {
          "original_text": "exact text as it appears",
          "normalized_value": "clean version without honorifics",
          "prefix_text": "HON.",
          "suffix_text": "",
          "casing_pattern": "uppercase|titlecase|lowercase|mixed",
          "page_number": 1,
          "source_area": "body|header|footer|table|signature_block|labeled_field",
          "nearby_label": "nearest label text",
          "context_before": "text before",
          "context_after": "text after",
          "semantic_context": "signature_block|labeled_field|header|footer|body|approval_block",
          "recommended_replacement_strategy": "replace_value_preserve_prefix|replace_exact_text_preserve_style|replace_signature_block_name"
        }
      ]
    }
  ],
  "needs_review_candidates": [
    {"original_text": "uncertain text", "suggested_display_name": "Field Name", "reason": "why uncertain", "confidence": "medium|low"}
  ],
  "summary": {"total": 0, "categories": {"people": 0, "dates": 0, "amounts": 0, "locations": 0, "contacts": 0, "organizations": 0}}
}

DETECT ALL OF: person names, mayor/signatory names, ALL CAPS names in signature blocks, names above titles like "Municipal Mayor", names after "Approved by" / "Certified by" / "Signed by" / "Noted by", positions/titles, organization/LGU/municipality names, dates, amounts, reference numbers, addresses, table cell values, header/footer values, label-value pairs ("Name:", "Date:"), repeated text.

GROUPING: "Juan Dela Cruz" + "HON. JUAN DELA CRUZ" + "Mayor Juan Dela Cruz" = ONE variable. Group by real-world identity.

SIGNATORIES: example_value = clean name only. prefix_text = "HON." or "Mayor". casing_pattern = "uppercase" if ALL CAPS. source_area = "signature_block". entity_role = "mayor_signatory".

Use snake_case names. sort_order = reading order. Return ONLY the JSON.
PROMPT;
    }

    private function parseResponse(array $response): array
    {
        $data = $this->ai->extractJson($response);

        // Accept both "variables" (current) and "canonical_variables" (future schema)
        if (!$data) {
            $raw = $this->ai->extractText($response);
            \Illuminate\Support\Facades\Log::error('VariableDetection: unexpected AI response', [
                'stop_reason' => $response['stop_reason'] ?? 'unknown',
                'usage'       => $response['usage'] ?? [],
                'raw'         => substr($raw, 0, 2000),
            ]);
            throw new \RuntimeException('AI returned an unexpected response format.');
        }

        // Normalize: prefer canonical_variables, fall back to variables
        if (!isset($data['variables']) || !is_array($data['variables'])) {
            if (isset($data['canonical_variables']) && is_array($data['canonical_variables'])) {
                $data['variables'] = $data['canonical_variables'];
            } else {
                $raw = $this->ai->extractText($response);
                \Illuminate\Support\Facades\Log::error('VariableDetection: no variables array in AI response', [
                    'stop_reason' => $response['stop_reason'] ?? 'unknown',
                    'usage'       => $response['usage'] ?? [],
                    'keys'        => array_keys($data),
                    'raw'         => substr($raw, 0, 2000),
                ]);
                throw new \RuntimeException('AI returned an unexpected response format.');
            }
        }

        return $data;
    }
}
