<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PHP client for the Python PDF microservice.
 *
 * The microservice runs on the same server at 127.0.0.1:5050 and handles:
 *   - /analyze        — pdfplumber text extraction with exact word positions
 *   - /find-positions — locate specific phrases and return exact bounding boxes
 *   - /generate       — overlay new values on a PDF at exact coordinates
 *
 * Why Python: pdfplumber reads the PDF content stream directly and returns
 * precise word-level bounding boxes in PDF points. No coordinate mismatches,
 * no approximations — the same coordinate system is used for extraction and
 * for generation, so values land exactly where the originals were.
 */
class PdfMicroserviceService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.pdf_microservice.url', 'http://127.0.0.1:5050');
    }

    public function isAvailable(): bool
    {
        try {
            $res = Http::timeout(3)->get($this->baseUrl . '/health');
            return $res->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Extract text and word-level positions from a PDF.
     *
     * @param  string $pdfPath  Absolute local path to the PDF file
     * @return array  { pages: [{page_number, width, height, text, words}], full_text }
     */
    public function analyze(string $pdfPath): array
    {
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF not found at: {$pdfPath}");
        }

        $response = Http::timeout(120)
            ->attach('pdf', file_get_contents($pdfPath), 'document.pdf')
            ->post($this->baseUrl . '/analyze');

        if (!$response->successful()) {
            Log::error('PdfMicroservice /analyze failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('PDF extraction service error: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Find the exact bounding boxes of specific phrases within a PDF.
     *
     * @param  string   $pdfPath  Absolute local path to the PDF
     * @param  string[] $terms    List of exact text strings to locate
     * @return array   { "term" => [{ page, x0, y0, x1, y1, x_pct, y_pct, w_pct, h_pct, font_size, is_bold }] }
     */
    public function findPositions(string $pdfPath, array $terms): array
    {
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF not found at: {$pdfPath}");
        }

        if (empty($terms)) {
            return [];
        }

        $response = Http::timeout(120)
            ->attach('pdf', file_get_contents($pdfPath), 'document.pdf')
            ->post($this->baseUrl . '/find-positions', [
                'terms' => json_encode(array_values($terms)),
            ]);

        if (!$response->successful()) {
            Log::error('PdfMicroservice /find-positions failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('PDF position service error: ' . $response->body());
        }

        return $response->json() ?? [];
    }

    /**
     * Generate a personalized PDF by overlaying new values at exact positions.
     *
     * @param  string $pdfPath      Absolute local path to the original template PDF
     * @param  array  $replacements List of replacement specs:
     *   [{ page, x0, y0, x1, y1, new_text, font_size, is_bold, text_align, font_color }]
     * @return string  Raw PDF bytes
     */
    public function generate(string $pdfPath, array $replacements): string
    {
        if (!file_exists($pdfPath)) {
            throw new \RuntimeException("PDF template not found at: {$pdfPath}");
        }

        $pdfB64 = base64_encode(file_get_contents($pdfPath));

        $response = Http::timeout(180)
            ->post($this->baseUrl . '/generate', [
                'pdf_b64'      => $pdfB64,
                'replacements' => $replacements,
            ]);

        if (!$response->successful()) {
            Log::error('PdfMicroservice /generate failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);
            throw new \RuntimeException('PDF generation service error: ' . $response->body());
        }

        return $response->body();
    }
}
