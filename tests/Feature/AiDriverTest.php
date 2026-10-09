<?php

use App\Enums\AiProvider;
use App\Models\Shop;
use App\Services\Ai\AiManager;
use App\Services\Ai\Drivers\ClaudeDriver;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\NullDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\Ai\Drivers\QwenDriver;
use Illuminate\Support\Facades\Http;

/**
 * All four providers are selectable in the campaign form, so all four must
 * actually reach their API and parse it. Before these drivers existed, choosing
 * claude/openai/gemini threw inside the manager and was caught into the null
 * driver — the campaign got bland template text that looked like model output.
 */
beforeEach(function () {
    // Any request not explicitly faked should fail the test rather than leave
    // the suite able to call a real provider.
    Http::preventStrayRequests();
});

/**
 * The JSON body a provider is expected to return.
 */
function aiPayload(): string
{
    return json_encode([
        'caption' => 'Big sale on protein powder!',
        // Leading "#" is stripped by parseResponse().
        'hashtags' => ['#fitness', 'protein', 'sale'],
        'call_to_action' => 'Shop now',
    ]);
}

function aiContext(): array
{
    return [
        'product_name' => 'Whey Protein',
        'product_price' => '2500',
        'landing_url' => 'https://shop.test/whey',
    ];
}

// --- Anthropic Claude ---------------------------------------------------------

it('generates content through the Claude messages endpoint', function () {
    config(['ai.drivers.claude.api_key' => 'sk-ant-test']);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'id' => 'msg_01',
        'model' => 'claude-opus-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => aiPayload()]],
    ])]);

    $result = app(AiManager::class)->driver('claude')->generateCampaignContent(aiContext());

    expect($result['caption'])->toBe('Big sale on protein powder!')
        ->and($result['hashtags'])->toBe(['fitness', 'protein', 'sale'])
        ->and($result['call_to_action'])->toBe('Shop now')
        ->and($result['provider'])->toBe('claude')
        ->and($result['model'])->toBe('claude-opus-5');

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/v1/messages')
            && $request->hasHeader('x-api-key', 'sk-ant-test')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            // Bearer auth is the OpenAI convention and is not accepted here.
            && ! $request->hasHeader('Authorization');
    });
});

it('constrains the Claude response with a json schema rather than a prefill', function () {
    config(['ai.drivers.claude.api_key' => 'sk-ant-test']);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => aiPayload()]],
    ])]);

    app(AiManager::class)->driver('claude')->generateCampaignContent(aiContext());

    Http::assertSent(function ($request) {
        $schema = $request['output_config']['format'] ?? [];

        return ($schema['type'] ?? null) === 'json_schema'
            && ($schema['schema']['additionalProperties'] ?? null) === false
            && $schema['schema']['required'] === ['caption', 'hashtags', 'call_to_action']
            // Current models reject a prefilled trailing assistant turn with a 400.
            && collect($request['messages'])->pluck('role')->doesntContain('assistant');
    });
});

it('reads the text block even when other block types precede it', function () {
    config(['ai.drivers.claude.api_key' => 'sk-ant-test']);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'stop_reason' => 'end_turn',
        'content' => [
            // Reasoning is on by default, so the answer is not content[0].
            ['type' => 'thinking', 'thinking' => ''],
            ['type' => 'text', 'text' => aiPayload()],
        ],
    ])]);

    $result = app(AiManager::class)->driver('claude')->generateCampaignContent(aiContext());

    expect($result['caption'])->toBe('Big sale on protein powder!');
});

it('falls back to template content when Claude declines the request', function () {
    config(['ai.drivers.claude.api_key' => 'sk-ant-test']);

    // A refusal is HTTP 200 with an empty content array — without an explicit
    // check that would silently become an empty caption.
    Http::fake(['api.anthropic.com/*' => Http::response([
        'stop_reason' => 'refusal',
        'stop_details' => ['type' => 'refusal', 'category' => 'cyber'],
        'content' => [],
    ])]);

    $result = app(AiManager::class)->driver('claude')->generateCampaignContent(aiContext());

    expect($result['caption'])->toContain('Whey Protein')
        ->and($result['raw']['fallback_reason'])->toBe('refusal');
});

it('falls back when Claude returns an error status', function () {
    config(['ai.drivers.claude.api_key' => 'sk-ant-test']);

    Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529)]);

    $result = app(AiManager::class)->driver('claude')->generateCampaignContent(aiContext());

    expect($result['raw']['fallback_reason'])->toBe('http_529')
        ->and($result['caption'])->not->toBeEmpty();
});

it('does not call Claude at all when no api key is configured', function () {
    config(['ai.drivers.claude.api_key' => null]);

    $result = app(AiManager::class)->driver('claude')->generateCampaignContent(aiContext());

    expect($result['raw']['fallback_reason'])->toBe('missing_api_key');

    Http::assertNothingSent();
});

// --- OpenAI ------------------------------------------------------------------

it('generates content through the OpenAI chat completions endpoint', function () {
    config(['ai.drivers.openai.api_key' => 'sk-openai-test']);

    Http::fake(['api.openai.com/*' => Http::response([
        'model' => 'gpt-4o-mini',
        'choices' => [['message' => ['content' => aiPayload()]]],
    ])]);

    $result = app(AiManager::class)->driver('openai')->generateCampaignContent(aiContext());

    expect($result['caption'])->toBe('Big sale on protein powder!')
        ->and($result['hashtags'])->toBe(['fitness', 'protein', 'sale'])
        ->and($result['provider'])->toBe('openai');

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/chat/completions')
            && $request->hasHeader('Authorization', 'Bearer sk-openai-test')
            && $request['response_format']['type'] === 'json_object';
    });
});

// --- Gemini ------------------------------------------------------------------

it('generates content through the Gemini generateContent endpoint', function () {
    config([
        'ai.drivers.gemini.api_key' => 'g-key',
        'ai.drivers.gemini.model' => 'gemini-2.0-flash',
    ]);

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'modelVersion' => 'gemini-2.0-flash',
        'candidates' => [['content' => ['parts' => [['text' => aiPayload()]]]]],
    ])]);

    $result = app(AiManager::class)->driver('gemini')->generateCampaignContent(aiContext());

    expect($result['caption'])->toBe('Big sale on protein powder!')
        ->and($result['provider'])->toBe('gemini')
        ->and($result['model'])->toBe('gemini-2.0-flash');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/models/gemini-2.0-flash:generateContent')
            // The key belongs in a header; ?key= would land in access logs.
            && $request->hasHeader('x-goog-api-key', 'g-key')
            && ! str_contains($request->url(), 'key=')
            && $request['generationConfig']['responseMimeType'] === 'application/json';
    });
});

it('falls back when Gemini returns a safety block with no parts', function () {
    config(['ai.drivers.gemini.api_key' => 'g-key']);

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['finishReason' => 'SAFETY']],
    ])]);

    $result = app(AiManager::class)->driver('gemini')->generateCampaignContent(aiContext());

    // No parsable payload, so parseResponse() yields defaults rather than throwing.
    expect($result['provider'])->toBe('gemini')
        ->and($result['call_to_action'])->toBe('Shop now')
        ->and($result['hashtags'])->toBe([]);
});

// --- Manager wiring ----------------------------------------------------------

it('resolves every provider offered in the campaign form', function () {
    // AiProvider::cases() drives the form's provider dropdown, so each case must
    // build a real driver — a missing arm used to degrade to NullDriver.
    $expected = [
        'qwen' => QwenDriver::class,
        'openai' => OpenAiDriver::class,
        'claude' => ClaudeDriver::class,
        'gemini' => GeminiDriver::class,
        'null' => NullDriver::class,
    ];

    foreach (AiProvider::cases() as $provider) {
        expect($expected)->toHaveKey($provider->value);
        expect(app(AiManager::class)->driver($provider->value))
            ->toBeInstanceOf($expected[$provider->value]);
    }
});

it('honours a per-shop provider override', function () {
    config(['ai.drivers.claude.api_key' => 'sk-ant-test']);

    $shop = Shop::factory()->create(['settings' => ['ai' => ['provider' => 'claude']]]);

    expect(app(AiManager::class)->forShop($shop))->toBeInstanceOf(ClaudeDriver::class);
});

it('falls back to the null driver for an unknown shop provider', function () {
    $shop = Shop::factory()->create(['settings' => ['ai' => ['provider' => 'not-a-provider']]]);

    expect(app(AiManager::class)->forShop($shop))->toBeInstanceOf(NullDriver::class);
});

it('pins a current Claude model id', function () {
    // claude-3-5-sonnet-latest was the previous default and is long superseded.
    expect(config('ai.drivers.claude.model'))->toBe('claude-opus-5')
        // Aliases are complete as-is; a date suffix would 404.
        ->and(config('ai.drivers.claude.model'))->not->toMatch('/-\d{8}$/');
});
