<?php

namespace App\Services\Ai\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic Claude driver, speaking the Messages API (`POST /v1/messages`).
 *
 * Three things differ from the OpenAI-compatible providers: authentication is an
 * `x-api-key` header rather than a bearer token, an `anthropic-version` header is
 * mandatory, and the answer arrives as a list of typed content blocks instead of
 * a single message string. JSON shape is enforced server-side through
 * `output_config.format`; assistant prefill — the older trick of seeding an
 * opening brace — is rejected with a 400 by current models.
 */
class ClaudeDriver extends AbstractAiDriver
{
    /**
     * Wire-format version of the Messages API. Unrelated to the model version.
     */
    private const API_VERSION = '2023-06-01';

    public function name(): string
    {
        return 'claude';
    }

    public function generateCampaignContent(array $context): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        $model = $context['model'] ?? ($this->config['model'] ?? 'claude-opus-5');

        if (empty($apiKey)) {
            // Graceful fallback so the workflow remains testable without keys.
            return $this->stubResponse($context, 'claude', $model, 'missing_api_key');
        }

        $prompt = $this->buildPrompt($context);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->acceptJson()
                ->post(rtrim((string) $this->config['base_url'], '/').'/messages', [
                    'model' => $model,
                    // This caps reasoning *and* answer tokens together, and
                    // reasoning is on by default, so it needs far more headroom
                    // than the few hundred tokens the caption itself costs. A
                    // tight value truncates the answer rather than saving money.
                    'max_tokens' => (int) ($this->config['max_tokens'] ?? 16000),
                    'system' => 'You produce only valid JSON matching the requested schema.',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'output_config' => [
                        'format' => [
                            'type' => 'json_schema',
                            'schema' => self::responseSchema(),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Claude content generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->stubResponse($context, 'claude', $model, 'http_'.$response->status());
            }

            $body = (array) $response->json();

            // A safety classifier can decline the request and still return HTTP
            // 200 with no content, so this must be checked before the blocks are
            // read — otherwise the campaign silently gets an empty caption.
            if (($body['stop_reason'] ?? null) === 'refusal') {
                Log::warning('Claude declined the content request', [
                    'stop_details' => $body['stop_details'] ?? null,
                ]);

                return $this->stubResponse($context, 'claude', $model, 'refusal');
            }

            $parsed = $this->parseResponse($this->extractText($body));

            return [
                'caption' => $parsed['caption'],
                'hashtags' => $parsed['hashtags'],
                'call_to_action' => $parsed['call_to_action'],
                'provider' => 'claude',
                'model' => $body['model'] ?? $model,
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Claude content generation exception', ['error' => $e->getMessage()]);

            return $this->stubResponse($context, 'claude', $model, 'exception');
        }
    }

    /**
     * Concatenate the text blocks of a Messages API response.
     *
     * `content` is a list of typed blocks — reasoning blocks may precede the
     * answer — so the payload is never simply `content[0]['text']`.
     *
     * @param  array<string, mixed>  $body
     */
    private function extractText(array $body): string
    {
        return collect($body['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');
    }

    /**
     * Schema handed to `output_config.format`. Structured outputs require every
     * object to declare `required` and `additionalProperties: false`, and do not
     * support length or numeric constraints — the caption limit stays in the
     * prompt instead.
     *
     * @return array<string, mixed>
     */
    private static function responseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'caption' => ['type' => 'string'],
                'hashtags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
                'call_to_action' => ['type' => 'string'],
            ],
            'required' => ['caption', 'hashtags', 'call_to_action'],
            'additionalProperties' => false,
        ];
    }
}
