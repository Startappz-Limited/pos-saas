<?php

namespace App\Services\Ai;

/**
 * Reads the token counts a provider reported in its raw response. The shapes
 * differ: OpenAI-compatible (qwen, openai) `usage.prompt_tokens`/
 * `completion_tokens`, Anthropic `usage.input_tokens`/`output_tokens`, Gemini
 * `usageMetadata.promptTokenCount`/`candidatesTokenCount`. A response that
 * reports none yields nulls and is charged the per-call credit.
 */
final class AiUsage
{
    /**
     * @return array{in: int|null, out: int|null}
     */
    public static function tokens(mixed $raw): array
    {
        if (! is_array($raw)) {
            return ['in' => null, 'out' => null];
        }

        $usage = $raw['usage'] ?? null;
        if (is_array($usage)) {
            return [
                'in' => self::int($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? null),
                'out' => self::int($usage['completion_tokens'] ?? $usage['output_tokens'] ?? null),
            ];
        }

        $meta = $raw['usageMetadata'] ?? null;
        if (is_array($meta)) {
            return ['in' => self::int($meta['promptTokenCount'] ?? null), 'out' => self::int($meta['candidatesTokenCount'] ?? null)];
        }

        return ['in' => null, 'out' => null];
    }

    private static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
