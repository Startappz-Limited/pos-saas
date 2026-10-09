<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Contracts\AiContentGenerator;
use Illuminate\Support\Str;

abstract class AbstractAiDriver implements AiContentGenerator
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config) {}

    /**
     * Build a prompt suitable for instruction-following models.
     *
     * @param  array<string, mixed>  $context
     */
    protected function buildPrompt(array $context): string
    {
        $tone = $context['tone'] ?? config('ai.defaults.tone');
        $language = $context['language'] ?? config('ai.defaults.language');
        $maxLen = config('ai.defaults.max_caption_length', 280);
        $hashtagCount = config('ai.defaults.hashtag_count', 8);
        $platform = $context['platform'] ?? 'social media';

        $product = $context['product_name'] ?? null;
        $price = $context['promo_price'] ?? $context['product_price'] ?? null;
        $currency = $context['currency'] ?? 'KES';

        $details = collect([
            'Campaign' => $context['campaign_name'] ?? null,
            'Campaign goal' => $context['campaign_type'] ?? null,
            'Product' => $product,
            'Product description' => $context['product_description'] ?? null,
            'Price' => $price ? "{$currency} {$price}" : null,
            'Promo code' => $context['promo_code'] ?? null,
            'Landing URL' => $context['landing_url'] ?? null,
            'Extra instructions' => $context['extra_instructions'] ?? null,
        ])->filter()->map(fn ($v, $k) => "- {$k}: {$v}")->implode("\n");

        return <<<PROMPT
You are a senior social-media marketer writing a {$platform} post in {$language}.
Tone: {$tone}.

Write a high-converting marketing post that drives clicks to the landing URL.

Constraints:
- Caption must be at most {$maxLen} characters and start with a strong hook.
- Provide exactly {$hashtagCount} relevant hashtags (no leading "#"), suitable for the niche.
- Provide one short call-to-action (max 5 words).
- Do not invent prices, claims, or URLs that are not provided.

Return STRICT JSON ONLY (no markdown fences, no commentary) with this exact shape:
{
  "caption": "string",
  "hashtags": ["tag1", "tag2"],
  "call_to_action": "string"
}

Context:
{$details}
PROMPT;
    }

    /**
     * Parse model output into the expected structure. Falls back to a best-effort
     * extraction so a slightly malformed response still produces usable content.
     *
     * @return array{caption: string, hashtags: array<int, string>, call_to_action: string}
     */
    protected function parseResponse(string $raw): array
    {
        $json = $this->extractJson($raw);

        $caption = (string) ($json['caption'] ?? Str::limit($raw, 240, ''));
        $hashtags = array_values(array_filter(array_map(
            fn ($tag) => ltrim(trim((string) $tag), '#'),
            (array) ($json['hashtags'] ?? [])
        )));
        $cta = (string) ($json['call_to_action'] ?? 'Shop now');

        return [
            'caption' => $caption,
            'hashtags' => $hashtags,
            'call_to_action' => $cta,
        ];
    }

    /**
     * Deterministic template content, used when a provider cannot be reached at
     * all — no API key, a non-2xx response, a refusal, or a thrown exception.
     * Campaign content generation must degrade rather than fail, and `raw`
     * carries the reason so the fallback is diagnosable instead of silent.
     *
     * @param  array<string, mixed>  $context
     * @return array{caption: string, hashtags: array<int, string>, call_to_action: string, provider: string, model: string, raw: array<string, string>}
     */
    protected function stubResponse(array $context, string $provider, string $model, string $reason): array
    {
        $product = $context['product_name'] ?? ($context['campaign_name'] ?? 'our latest offer');
        $price = $context['promo_price'] ?? $context['product_price'] ?? null;
        $currency = $context['currency'] ?? 'KES';
        $url = $context['landing_url'] ?? null;
        $promo = $context['promo_code'] ?? null;

        $caption = "🔥 Don't miss out on {$product}!"
            .($price ? " Now only {$currency} {$price}." : '')
            .($promo ? " Use code {$promo} at checkout." : '')
            .($url ? " Tap the link to shop ➡ {$url}" : '');

        return [
            'caption' => $caption,
            'hashtags' => ['fitness', 'wellness', 'shopnow', 'health', 'lifestyle', 'training', 'gym', 'sale'],
            'call_to_action' => 'Shop now',
            'provider' => $provider,
            'model' => $model,
            'raw' => ['fallback_reason' => $reason],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractJson(string $raw): array
    {
        $trimmed = trim($raw);

        // Strip markdown code fences if model wrapped JSON in them.
        $trimmed = preg_replace('/^```(?:json)?|```$/m', '', $trimmed) ?? $trimmed;

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fallback: capture the first {...} block.
        if (preg_match('/\{.*\}/s', $trimmed, $m) === 1) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
