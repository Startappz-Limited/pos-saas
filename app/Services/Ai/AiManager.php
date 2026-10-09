<?php

namespace App\Services\Ai;

use App\Models\Shop;
use App\Services\Ai\Contracts\AiContentGenerator;
use App\Services\Ai\Drivers\ClaudeDriver;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\NullDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\Ai\Drivers\QwenDriver;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Resolves an AI content driver based on shop preferences with a config-level
 * fallback. New providers can be registered via `extend()`.
 */
class AiManager
{
    /**
     * @var array<string, callable(array<string, mixed>): AiContentGenerator>
     */
    protected array $customDrivers = [];

    /**
     * @var array<string, AiContentGenerator>
     */
    protected array $resolved = [];

    public function driver(?string $name = null): AiContentGenerator
    {
        $name ??= (string) config('ai.default', 'null');

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $config = (array) config("ai.drivers.{$name}", []);
        if (empty($config)) {
            throw new InvalidArgumentException("AI driver [{$name}] is not configured.");
        }

        return $this->resolved[$name] = $this->build($name, $config);
    }

    public function forShop(?Shop $shop): AiContentGenerator
    {
        $preferred = $shop?->settings['ai']['provider'] ?? null;

        try {
            return $this->driver($preferred ?: null);
        } catch (InvalidArgumentException $e) {
            // Falling back keeps campaign generation working, but log it: an
            // unrecognised provider would otherwise produce template output that
            // looks like the model simply wrote something bland.
            Log::warning('AI driver unavailable, falling back to null driver', [
                'requested' => $preferred ?: config('ai.default'),
                'shop_id' => $shop?->getKey(),
                'error' => $e->getMessage(),
            ]);

            return $this->driver('null');
        }
    }

    /**
     * @param  callable(array<string, mixed>): AiContentGenerator  $factory
     */
    public function extend(string $name, callable $factory): self
    {
        $this->customDrivers[$name] = $factory;
        unset($this->resolved[$name]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function build(string $name, array $config): AiContentGenerator
    {
        if (isset($this->customDrivers[$name])) {
            return ($this->customDrivers[$name])($config);
        }

        return match ($config['driver'] ?? $name) {
            'qwen' => new QwenDriver($config),
            'openai' => new OpenAiDriver($config),
            'claude' => new ClaudeDriver($config),
            'gemini' => new GeminiDriver($config),
            'null' => new NullDriver($config),
            default => throw new InvalidArgumentException(
                "Unsupported AI driver [{$name}]. Register one via AiManager::extend()."
            ),
        };
    }
}
