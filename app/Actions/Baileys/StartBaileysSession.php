<?php

namespace App\Actions\Baileys;

use App\Enums\BaileysSessionStatus;
use App\Models\BaileysSession;
use App\Models\Shop;
use Carbon\Carbon;
use Startappz\WaGateway\Exceptions\GatewayException;
use Startappz\WaGateway\WaGateway;

class StartBaileysSession
{
    public function __construct(protected WaGateway $gateway) {}

    public function execute(Shop $shop, ?int $createdBy = null, string $name = 'Default'): BaileysSession
    {
        $session = BaileysSession::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => $name],
            ['created_by' => $createdBy, 'status' => BaileysSessionStatus::Pending],
        );

        // Outside any transaction: the gateway call is an HTTP round trip. The
        // QR itself is not in this response; it arrives as a session.qr webhook
        // and the session page fetches it if the webhook hasn't landed yet.
        try {
            $remote = $this->gateway->startSession($session->session_key, $session->name);
        } catch (GatewayException $e) {
            $session->update([
                'status' => BaileysSessionStatus::Failed,
                'last_error' => $e->getMessage(),
            ]);

            return $session->fresh();
        }

        $status = BaileysSessionStatus::fromGateway($remote['status'] ?? null);

        if ($status === BaileysSessionStatus::Connected) {
            $session->markConnected(
                jid: $remote['jid'] ?? null,
                phoneNumber: $remote['phone_number'] ?? null,
                displayName: $remote['display_name'] ?? null,
            );

            return $session->fresh();
        }

        $session->update([
            'status' => $status,
            'qr_code' => null,
            'qr_expires_at' => isset($remote['qr_expires_at']) ? Carbon::parse($remote['qr_expires_at']) : null,
            'last_error' => null,
        ]);

        return $session->fresh();
    }
}
