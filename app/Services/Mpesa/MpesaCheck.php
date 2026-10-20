<?php

namespace App\Services\Mpesa;

/**
 * The outcome of a test query to the M-Pesa API.
 */
final readonly class MpesaCheck
{
    public function __construct(public bool $ok, public ?string $error = null) {}

    public static function passed(): self
    {
        return new self(true);
    }

    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
