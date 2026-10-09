<?php

namespace App\Enums;

enum AiProvider: string
{
    case QWEN = 'qwen';
    case OPENAI = 'openai';
    case CLAUDE = 'claude';
    case GEMINI = 'gemini';
    case NULL = 'null';

    public function label(): string
    {
        return match ($this) {
            self::QWEN => 'Qwen (Alibaba DashScope)',
            self::OPENAI => 'OpenAI (GPT)',
            self::CLAUDE => 'Anthropic Claude',
            self::GEMINI => 'Google Gemini',
            self::NULL => 'None / Disabled',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->label()])
            ->all();
    }
}
