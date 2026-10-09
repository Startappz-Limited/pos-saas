<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Driver
    |--------------------------------------------------------------------------
    |
    | Each shop may also override its driver by storing it in
    | Shop.settings.ai.provider. When that value is missing the manager falls
    | back to this default.
    |
    */

    'default' => env('AI_DRIVER', 'qwen'),

    /*
    |--------------------------------------------------------------------------
    | Driver Definitions
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'qwen' => [
            'driver' => 'qwen',
            'api_key' => env('QWEN_API_KEY'),
            'base_url' => env('QWEN_BASE_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'),
            'model' => env('QWEN_MODEL', 'qwen-plus'),
            'timeout' => env('QWEN_TIMEOUT', 30),
        ],

        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'timeout' => env('OPENAI_TIMEOUT', 30),
        ],

        'claude' => [
            'driver' => 'claude',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
            'timeout' => env('ANTHROPIC_TIMEOUT', 30),
            // Caps reasoning and answer tokens together, and reasoning is on by
            // default, so this is deliberately far above the caption's own size.
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 16000),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            // Verify against Google's current model list before relying on this;
            // Gemini retires model ids on a published schedule.
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'timeout' => env('GEMINI_TIMEOUT', 30),
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Generation Parameters
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'tone' => env('AI_DEFAULT_TONE', 'enthusiastic and friendly'),
        'language' => env('AI_DEFAULT_LANGUAGE', 'English'),
        'max_caption_length' => 280,
        'hashtag_count' => 8,
    ],

];
