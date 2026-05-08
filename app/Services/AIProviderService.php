<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AIProviderService
{
    private string $apiKey;
    private string $apiVersion;
    private string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey     = config('services.anthropic.key');
        $this->apiVersion = config('services.anthropic.api_version', '2023-06-01');
    }

    public function fastModel(): string
    {
        return config('services.anthropic.model_fast', 'claude-haiku-4-5-20251001');
    }

    public function smartModel(): string
    {
        return config('services.anthropic.model_smart', 'claude-sonnet-4-6');
    }

    /**
     * Send a message to the Anthropic API.
     *
     * @param  array  $messages
     * @param  string $model
     * @param  int    $maxTokens
     * @param  string $system
     * @param  array  $betaHeaders  e.g. ['pdfs-2024-09-25']
     * @return array
     */
    public function messages(
        array  $messages,
        string $model      = '',
        int    $maxTokens  = 2048,
        string $system     = '',
        array  $betaHeaders = []
    ): array {
        $model = $model ?: $this->fastModel();

        $headers = [
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'content-type'      => 'application/json',
        ];

        if (!empty($betaHeaders)) {
            $headers['anthropic-beta'] = implode(',', $betaHeaders);
        }

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $messages,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        $response = Http::withHeaders($headers)
            ->timeout(120)
            ->post($this->baseUrl . '/messages', $payload);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Anthropic API error ' . $response->status() . ': ' . $response->body()
            );
        }

        return $response->json();
    }

    /**
     * Extract the text content from an API response.
     */
    public function extractText(array $response): string
    {
        return $response['content'][0]['text'] ?? '';
    }

    /**
     * Extract and decode JSON from an API response.
     */
    public function extractJson(array $response): ?array
    {
        $text = $this->extractText($response);

        // Strip markdown code fences if present
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```$/m', '', $text);
        $text = trim($text);

        // Try direct decode first
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fall back: extract the first {...} block from the text
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
