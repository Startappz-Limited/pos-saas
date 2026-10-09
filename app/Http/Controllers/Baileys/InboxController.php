<?php

namespace App\Http\Controllers\Baileys;

use App\Actions\Baileys\SendBaileysMediaMessage;
use App\Actions\Baileys\SendBaileysMessage;
use App\Enums\BaileysChatType;
use App\Enums\BaileysMessageType;
use App\Http\Controllers\Controller;
use App\Models\BaileysChat;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Support\BaileysJid;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InboxController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BaileysSession::class);

        $sessions = BaileysSession::query()->connected()->with('shop:id,name')->get();
        $session = null;
        if ($request->filled('session')) {
            $session = $sessions->firstWhere('uuid', $request->string('session'));
        }
        $session ??= $sessions->first();

        $chats = collect();
        if ($session) {
            $chats = BaileysChat::query()
                ->where('baileys_session_id', $session->id)
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
                ->orderByDesc('last_message_at')
                ->limit(100)
                ->get();
        }

        $activeChat = null;
        $messages = collect();

        if ($session && $request->filled('chat')) {
            $activeChat = $chats->firstWhere('id', $request->integer('chat'))
                ?? BaileysChat::query()->where('baileys_session_id', $session->id)->find($request->integer('chat'));

            if ($activeChat) {
                $messages = BaileysMessage::query()
                    ->where('baileys_chat_id', $activeChat->id)
                    ->orderBy('created_at')
                    ->limit(200)
                    ->get();

                $activeChat->update(['unread_count' => 0]);
            }
        }

        return view('baileys.inbox.index', [
            'sessions' => $sessions,
            'session' => $session,
            'chats' => $chats,
            'activeChat' => $activeChat,
            'messages' => $messages,
            'chatTypes' => BaileysChatType::cases(),
        ]);
    }

    public function send(Request $request, BaileysChat $chat, SendBaileysMessage $action): RedirectResponse|JsonResponse
    {
        $this->authorize('viewAny', BaileysSession::class);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:4096'],
        ]);

        $session = $chat->session;

        $message = $action->execute(
            session: $session,
            toJid: $chat->jid,
            text: $data['text'],
            sentBy: auth()->id(),
            customerId: null,
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => [
                    'id' => $message->id,
                    'content' => $message->content,
                    'direction' => $message->direction->value,
                    'status' => $message->status->value,
                    'sent_at' => ($message->sent_at ?? $message->created_at)->format('H:i'),
                ],
            ]);
        }

        return back()->with('success', 'Message sent.');
    }

    /**
     * Upload a file from the composer and send it as a Baileys media message.
     */
    public function sendMedia(Request $request, BaileysChat $chat, SendBaileysMediaMessage $action): JsonResponse|RedirectResponse
    {
        $this->authorize('viewAny', BaileysSession::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:32768'], // 32MB
            'caption' => ['nullable', 'string', 'max:1024'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $session = $chat->session;

        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $mediaType = match (true) {
            str_starts_with($mime, 'image/') && $mime !== 'image/webp' => BaileysMessageType::Image,
            $mime === 'image/webp' => BaileysMessageType::Sticker,
            str_starts_with($mime, 'video/') => BaileysMessageType::Video,
            str_starts_with($mime, 'audio/') => BaileysMessageType::Audio,
            default => BaileysMessageType::Document,
        };

        $folder = 'baileys/'.$session->uuid;
        $path = $file->store($folder, 'public');
        $url = asset('storage/'.$path);

        // Read bytes for inline (base64) delivery to the gateway so it never
        // has to fetch our public URL (firewalls / DNS / TLS issues).
        $bytes = @file_get_contents($file->getRealPath() ?: storage_path('app/public/'.$path));
        $base64 = $bytes !== false ? base64_encode($bytes) : null;

        $message = $action->execute(
            session: $session,
            toJid: $chat->jid,
            mediaType: $mediaType->value,
            url: $url,
            caption: $data['caption'] ?? null,
            extras: [
                'mimetype' => $mime,
                'filename' => $file->getClientOriginalName(),
                'data' => $base64,
            ],
            sentBy: auth()->id(),
            customerId: null,
        );

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => $message->status->value !== 'failed',
                'message' => [
                    'id' => $message->id,
                    'content' => $message->content,
                    'direction' => $message->direction->value,
                    'status' => $message->status->value,
                    'type' => $message->type->value,
                    'media_url' => $message->media_url,
                    'media_mime' => $message->media_mime,
                    'media_filename' => $message->media_filename,
                    'sent_at' => ($message->sent_at ?? $message->created_at)->format('H:i'),
                ],
            ]);
        }

        return back()->with('success', 'Media sent.');
    }

    /**
     * Open (or create) a chat with an arbitrary phone number on a connected session.
     */
    public function startChat(Request $request, BaileysSession $session): RedirectResponse
    {
        $this->authorize('view', $session);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $jid = BaileysJid::fromPhone($data['phone']);

        if (! $jid) {
            return back()->withErrors(['phone' => 'Invalid phone number.'])->withInput();
        }

        $chat = BaileysChat::query()->firstOrCreate(
            [
                'baileys_session_id' => $session->id,
                'jid' => $jid,
            ],
            [
                'shop_id' => $session->shop_id,
                'type' => BaileysChatType::Private,
                'name' => $data['name'] ?? $data['phone'],
            ],
        );

        return redirect()->route('baileys.inbox.index', [
            'session' => $session->uuid,
            'chat' => $chat->id,
        ]);
    }

    /**
     * Map a chat (typically a `@lid` JID) to a real phone number + optional display name.
     */
    public function mapChat(Request $request, BaileysChat $chat): RedirectResponse
    {
        $this->authorize('view', $chat->session);

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $jid = BaileysJid::fromPhone($data['phone']);

        if (! $jid) {
            return back()->withErrors(['phone' => 'Invalid phone number.'])->withInput();
        }

        $digits = preg_replace('/\D+/', '', $data['phone']) ?? '';

        $chat->update([
            'phone' => $digits !== '' ? $digits : $data['phone'],
            'name' => $data['name'] ?? $chat->name ?? $data['phone'],
        ]);

        return back()->with('success', 'Chat mapped.');
    }

    /**
     * Server-Sent Events stream of new messages for a chat plus chat-list deltas.
     * Cheap real-time: one long-lived HTTP connection per open tab, no extra services.
     */
    public function stream(Request $request, BaileysChat $chat): StreamedResponse
    {
        $this->authorize('view', $chat->session);

        // Release the session lock immediately so other requests from the same
        // browser (sending messages, navigating) are not blocked by the stream.
        $request->session()->save();

        $sessionId = $chat->baileys_session_id;
        $chatId = $chat->id;
        $sinceMessageId = (int) $request->query('since_message_id', 0);
        $sinceListTs = (string) $request->query('since_list', '');
        $sinceUpdatedAt = now()->toDateTimeString();

        if ($sinceMessageId === 0) {
            $sinceMessageId = (int) BaileysMessage::query()
                ->where('baileys_chat_id', $chatId)
                ->max('id');
        }

        $response = new StreamedResponse(function () use ($sessionId, $chatId, &$sinceMessageId, &$sinceListTs, &$sinceUpdatedAt) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            ignore_user_abort(false);
            @set_time_limit(0);

            $deadline = microtime(true) + 50.0; // reconnect every ~50s
            $lastPing = microtime(true);

            echo "retry: 1500\n\n";
            @flush();

            while (microtime(true) < $deadline) {
                if (connection_aborted()) {
                    break;
                }

                // 1) New messages in this chat
                $newMessages = BaileysMessage::query()
                    ->where('baileys_chat_id', $chatId)
                    ->where('id', '>', $sinceMessageId)
                    ->orderBy('id')
                    ->limit(50)
                    ->get(['id', 'content', 'direction', 'status', 'type', 'media_url', 'media_mime', 'media_filename', 'sent_at', 'created_at']);

                if ($newMessages->isNotEmpty()) {
                    $payload = $newMessages->map(fn (BaileysMessage $m) => [
                        'id' => $m->id,
                        'content' => $m->content,
                        'direction' => $m->direction->value,
                        'status' => $m->status->value,
                        'type' => $m->type->value,
                        'media_url' => $m->media_url,
                        'media_mime' => $m->media_mime,
                        'media_filename' => $m->media_filename,
                        'sent_at' => ($m->sent_at ?? $m->created_at)->format('H:i'),
                    ])->all();

                    echo 'event: messages'."\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                    @flush();

                    $sinceMessageId = (int) $newMessages->last()->id;
                }

                // 1b) Status changes for outbound messages already on-screen
                $statusUpdates = BaileysMessage::query()
                    ->where('baileys_chat_id', $chatId)
                    ->where('direction', 'outbound')
                    ->where('id', '<=', $sinceMessageId)
                    ->where('updated_at', '>', $sinceUpdatedAt)
                    ->orderBy('updated_at')
                    ->limit(50)
                    ->get(['id', 'status', 'updated_at']);

                if ($statusUpdates->isNotEmpty()) {
                    $payload = $statusUpdates->map(fn (BaileysMessage $m) => [
                        'id' => $m->id,
                        'status' => $m->status->value,
                    ])->all();

                    echo 'event: status'."\n";
                    echo 'data: '.json_encode($payload)."\n\n";
                    @flush();

                    $sinceUpdatedAt = (string) $statusUpdates->max('updated_at');
                }

                // 2) Chat list deltas (any chat in the same session whose
                //    last_message_at moved, plus its current unread_count)
                $listQuery = BaileysChat::query()
                    ->where('baileys_session_id', $sessionId)
                    ->orderByDesc('last_message_at')
                    ->limit(20);

                if ($sinceListTs !== '') {
                    $listQuery->where('last_message_at', '>', $sinceListTs);
                }

                $changedChats = $listQuery->get(['id', 'name', 'phone', 'jid', 'type', 'unread_count', 'last_message_at']);

                if ($changedChats->isNotEmpty()) {
                    $payload = $changedChats->map(fn (BaileysChat $c) => [
                        'id' => $c->id,
                        'name' => $c->name,
                        'phone' => $c->phone,
                        'jid' => $c->jid,
                        'type' => $c->type->value,
                        'unread_count' => $c->unread_count,
                        'last_message_at' => optional($c->last_message_at)->toIso8601String(),
                        'last_message_human' => optional($c->last_message_at)->diffForHumans(['short' => true]),
                    ])->all();

                    echo 'event: chats'."\n";
                    echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";
                    @flush();

                    $sinceListTs = (string) $changedChats->max('last_message_at');
                }

                // Heartbeat every 15s so proxies don't kill the connection
                if (microtime(true) - $lastPing > 15) {
                    echo ": ping\n\n";
                    @flush();
                    $lastPing = microtime(true);
                }

                usleep(1_000_000); // 1s tick
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // tell Nginx not to buffer

        return $response;
    }
}
