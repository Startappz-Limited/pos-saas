<?php

namespace App\Services\Mpesa;

use App\Contracts\MpesaGateway;

/**
 * For tests: passes unless told to fail, and remembers what it was asked.
 */
class FakeMpesaGateway implements MpesaGateway
{
    /** @var list<array{shortcode: string, type: string, credentials: array<string, mixed>}> */
    public array $checks = [];

    public function __construct(private ?string $failure = null) {}

    public function failWith(string $error): self
    {
        $this->failure = $error;

        return $this;
    }

    public function verify(string $shortcode, string $type, array $credentials): MpesaCheck
    {
        $this->checks[] = compact('shortcode', 'type', 'credentials');

        return $this->failure === null ? MpesaCheck::passed() : MpesaCheck::failed($this->failure);
    }
}
