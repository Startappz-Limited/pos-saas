<?php

namespace App\Services\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI driver, using the chat-completions endpoint.
 *
 * Requests use `response_format: json_object` rather than a JSON schema: the
 * `base_url` is configurable, so this driver is routinely pointed at
 * OpenAI-compatible gateways and self-hosted endpoints that accept `json_object`
 * but reject `json_schema`. The prompt already demands a strict shape and
 * `parseResponse()` tolerates the rest.
 */
class OpenAiDriver extends AbstractAiDriver
{
    public function name(): string
    {
        return 'openai';
    }

    public function generateCampaignContent(array $context): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        $model = $context['model'] ?? ($this->config['model'] ?? 'gpt-4o-mini');

        if (empty($apiKey)) {
            // Graceful fallback so the workflow remains testable without keys.
            return $this->stubResponse($context, 'openai', $model, 'missing_api_key');
        }

        $prompt = $this->buildPrompt($context);

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->acceptJson()
                ->post(rtrim((string) $this->config['base_url'], '/').'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You produce only valid JSON.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('OpenAI content generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->stubResponse($context, 'openai', $model, 'http_'.$response->status());
            }

            $body = (array) $response->json();
            $raw = $body['choices'][0]['message']['content'] ?? '';
            $parsed = $this->parseResponse((string) $raw);

            return [
                'caption' => $parsed['caption'],
                'hashtags' => $parsed['hashtags'],
                'call_to_action' => $parsed['call_to_action'],
                'provider' => 'openai',
                'model' => $body['model'] ?? $model,
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI content generation exception', ['error' => $e->getMessage()]);

            return $this->stubResponse($context, 'openai', $model, 'exception');
        }
    }
}
