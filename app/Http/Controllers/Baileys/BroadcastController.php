<?php

namespace App\Http\Controllers\Baileys;

use App\Actions\Baileys\SendBaileysMessage;
use App\Actions\Baileys\UpdateBaileysStatus;
use App\Enums\BaileysMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\BaileysSession;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Startappz\WaGateway\Exceptions\GatewayException;
use Startappz\WaGateway\WaGateway;

/**
 * Composer for posting to groups and the status feed via a connected Baileys
 * session. (Channels were dropped: WhatsApp gives no way to list the channels
 * a number follows, so that list was always empty.)
 */
class BroadcastController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected WaGateway $gateway) {}

    public function show(Request $request, UpdateBaileysStatus $status): View
    {
        $this->authorize('viewAny', BaileysSession::class);

        $sessions = BaileysSession::query()->connected()->with('shop:id,name')->get();
        $session = $sessions->firstWhere('uuid', $request->string('session')) ?? $sessions->first();

        $groups = [];
        $groupsError = null;
        $statusAudience = 0;

        if ($session) {
            try {
                $groups = $this->gateway->groups($session->session_key);
            } catch (GatewayException $e) {
                $groupsError = $e->getMessage();
            }

            $statusAudience = count($status->audience($session));
        }

        return view('baileys.broadcast.show', compact('sessions', 'session', 'groups', 'groupsError', 'statusAudience'));
    }

    public function postToGroup(Request $request, BaileysSession $session, SendBaileysMessage $action): RedirectResponse
    {
        $this->authorize('view', $session);

        $data = $request->validate([
            'jid' => ['required', 'string', 'ends_with:@g.us'],
            'text' => ['required', 'string', 'max:4096'],
        ]);

        $message = $action->execute($session, $data['jid'], $data['text'], auth()->id());

        return $message->status === BaileysMessageStatus::Failed
            ? back()->with('error', __('Not posted: :reason', ['reason' => $message->error_message]))
            : back()->with('success', __('Posted to group.'));
    }

    public function updateStatus(Request $request, BaileysSession $session, UpdateBaileysStatus $action): RedirectResponse
    {
        $this->authorize('view', $session);

        $data = $request->validate([
            'type' => ['required', 'in:text,image,video'],
            'text' => ['nullable', 'string', 'max:1000', 'required_if:type,text'],
            'url' => ['nullable', 'url', 'required_unless:type,text'],
            'caption' => ['nullable', 'string', 'max:1000'],
            // The gateway accepts only a hex colour and fails the whole post otherwise.
            'background_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
        ]);

        if ($data['type'] !== 'text') {
            unset($data['background_color']);
        }

        $message = $action->execute($session, array_filter($data), auth()->id());

        return $message->status === BaileysMessageStatus::Failed
            ? back()->with('error', __('Status not posted: :reason', ['reason' => $message->error_message]))
            : back()->with('success', __('Status posted to :count of your recent WhatsApp contacts.', [
                'count' => $message->payload['recipient_count'] ?? 0,
            ]));
    }
}
