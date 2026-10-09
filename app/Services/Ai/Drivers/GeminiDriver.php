<?php

namespace App\Services\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini driver, using the `generateContent` endpoint.
 *
 * The model id is part of the path rather than the body, the prompt is wrapped in
 * `contents[].parts[]`, and the key travels in an `x-goog-api-key` header — the
 * `?key=` query form Google also accepts would put the credential in access logs
 * and exception traces. JSON is requested via `responseMimeType` rather than a
 * `responseSchema`, since Gemini's schema dialect differs from JSON Schema and
 * the prompt already pins the shape.
 */
class GeminiDriver extends AbstractAiDriver
{
    public function name(): string
    {
        return 'gemini';
    }

    public function generateCampaignContent(array $context): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        $model = $context['model'] ?? ($this->config['model'] ?? 'gemini-2.0-flash');

        if (empty($apiKey)) {
            // Graceful fallback so the workflow remains testable without keys.
            return $this->stubResponse($context, 'gemini', $model, 'missing_api_key');
        }

        $prompt = $this->buildPrompt($context);
        $endpoint = rtrim((string) $this->config['base_url'], '/').'/models/'.$model.':generateContent';

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->acceptJson()
                ->post($endpoint, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini content generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->stubResponse($context, 'gemini', $model, 'http_'.$response->status());
            }

            $body = (array) $response->json();

            // A safety block returns 200 with a finishReason and no parts, so an
            // empty payload here is expected rather than exceptional.
            $parsed = $this->parseResponse($this->extractText($body));

            return [
                'caption' => $parsed['caption'],
                'hashtags' => $parsed['hashtags'],
                'call_to_action' => $parsed['call_to_action'],
                'provider' => 'gemini',
                'model' => $body['modelVersion'] ?? $model,
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Gemini content generation exception', ['error' => $e->getMessage()]);

            return $this->stubResponse($context, 'gemini', $model, 'exception');
        }
    }

    /**
     * Concatenate the text parts of the first candidate.
     *
     * @param  array<string, mixed>  $body
     */
    private function extractText(array $body): string
    {
        return collect($body['candidates'][0]['content']['parts'] ?? [])
            ->pluck('text')
            ->filter()
            ->implode('');
    }
}
