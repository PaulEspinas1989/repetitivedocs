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

            foreach ($variableList as $v) {
                TemplateVariable::create([
                    'template_id'     => $template->id,
                    'workspace_id'    => $doc->workspace_id,
                    'name'            => Str::snake($v['name'] ?? Str::random(8)),
                    'label'           => $v['label'] ?? $v['name'] ?? 'Unknown',
                    'type'            => $v['type'] ?? 'text',
                    'description'     => $v['description'] ?? null,
                    'example_value'   => $v['example_value'] ?? null,
                    'is_required'     => $v['is_required'] ?? true,
                    'sort_order'      => $v['sort_order'] ?? 0,
                    'approval_status' => 'pending',
                    'ai_suggested'    => true,
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
            model:       $this->ai->fastModel(),
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
You are an AI document analyzer for a document automation platform called RepetitiveDocs.

The user uploaded a document called "{$templateName}". Your task is to:
{$docTextInstruction}{$taskNumber}. Identify all fields that CHANGE from one document to the next

Return ONLY a valid JSON object with this exact structure (no markdown, no explanation):
{
{$docTextField}  "variables": [
    {
      "name": "snake_case_name",
      "label": "Human Readable Label",
      "type": "text|date|number|currency|email|phone|address|select",
      "description": "Brief description of what this field represents",
      "example_value": "The exact value found in this document",
      "is_required": true,
      "sort_order": 1
    }
  ],
  "summary": {
    "total": 10,
    "categories": {
      "people": 3,
      "dates": 2,
      "amounts": 2,
      "locations": 1,
      "contacts": 1,
      "organizations": 1
    }
  }
}

Type guide:
- text: names, titles, descriptions, IDs, reference numbers, job titles
- date: any date or time value
- number: quantities, counts, percentages
- currency: monetary amounts (₱, $, etc.)
- email: email addresses
- phone: phone or mobile numbers
- address: physical addresses
- select: fields with limited choices (e.g., civil status, department, payment type)

Rules:
- Only include fields that REALISTICALLY CHANGE between documents
- Do NOT include static headers, fixed company boilerplate, or constant text
- Use snake_case for all names (e.g., client_name, invoice_date)
- Keep labels concise (2–4 words)
- sort_order must reflect document order (top to bottom)
- Return ONLY the JSON object — nothing else
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
