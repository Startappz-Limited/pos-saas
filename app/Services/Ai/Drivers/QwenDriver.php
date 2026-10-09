<?php

namespace App\Services\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QwenDriver extends AbstractAiDriver
{
    public function name(): string
    {
        return 'qwen';
    }

    public function generateCampaignContent(array $context): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        $model = $context['model'] ?? ($this->config['model'] ?? 'qwen-plus');

        if (empty($apiKey)) {
            // Graceful fallback so the workflow remains testable without keys.
            return $this->stubResponse($context, 'qwen', $model, 'missing_api_key');
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
                Log::warning('Qwen content generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->stubResponse($context, 'qwen', $model, 'http_'.$response->status());
            }

            $body = $response->json();
            $raw = $body['choices'][0]['message']['content'] ?? '';
            $parsed = $this->parseResponse((string) $raw);

            return [
                'caption' => $parsed['caption'],
                'hashtags' => $parsed['hashtags'],
                'call_to_action' => $parsed['call_to_action'],
                'provider' => 'qwen',
                'model' => $model,
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Qwen content generation exception', ['error' => $e->getMessage()]);

            return $this->stubResponse($context, 'qwen', $model, 'exception');
        }
    }
}
