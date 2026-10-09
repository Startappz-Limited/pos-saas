<?php

namespace App\Notifications\Messages;

class WhatsAppTemplateMessage
{
    /**
     * @param  array<int, array{type: string, parameters: array<int, array<string, mixed>>}>  $components
     */
    public function __construct(
        public string $templateName,
        public string $languageCode = 'en',
        public array $components = [],
    ) {}

    /**
     * Add body parameters to the template.
     *
     * @param  array<int, string>  $parameters
     */
    public function bodyParameters(array $parameters): static
    {
        $this->components[] = [
            'type' => 'body',
            'parameters' => array_map(fn(string $value) => [
                'type' => 'text',
                'text' => $value,
            ], $parameters),
        ];

        return $this;
    }

    /**
     * Add a URL button parameter (dynamic suffix for URL button).
     */
    public function buttonUrl(int $index, string $url): static
    {
        $this->components[] = [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => $index,
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => $url,
                ],
            ],
        ];

        return $this;
    }
}
