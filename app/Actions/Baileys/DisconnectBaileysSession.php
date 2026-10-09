<?php

namespace App\Actions\Baileys;

use App\Models\BaileysSession;
use Startappz\WaGateway\Exceptions\GatewayException;
use Startappz\WaGateway\WaGateway;

class DisconnectBaileysSession
{
    public function __construct(protected WaGateway $gateway) {}

    /**
     * Unlinks the device from WhatsApp, then records it. A session the gateway
     * no longer has counts as unlinked.
     *
     * @throws GatewayException when the gateway can't be reached. The session is
     *                          left as it was: showing it disconnected while
     *                          the phone stays linked would hide a live device.
     */
    public function execute(BaileysSession $session, ?string $reason = null): BaileysSession
    {
        $this->gateway->deleteSession($session->session_key);

        $session->markDisconnected($reason ?? 'Disconnected by user');

        return $session->fresh();
    }
}
