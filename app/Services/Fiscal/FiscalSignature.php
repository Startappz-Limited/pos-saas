<?php

namespace App\Services\Fiscal;

use Carbon\CarbonInterface;

/**
 * What a fiscal provider (KRA eTIMS) returns for a sale and the receipt must
 * show: its control code, the device and invoice numbers it was signed
 * under, and the address its QR code points to.
 */
final readonly class FiscalSignature
{
    public function __construct(
        public string $provider,
        public string $controlCode,
        public ?string $deviceSerial = null,
        public ?string $invoiceNumber = null,
        public ?string $qrUrl = null,
        public ?CarbonInterface $signedAt = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'control_code' => $this->controlCode,
            'device_serial' => $this->deviceSerial,
            'invoice_number' => $this->invoiceNumber,
            'qr_url' => $this->qrUrl,
            'signed_at' => $this->signedAt?->toIso8601String(),
        ];
    }
}
