'use strict';

const fs = require('fs');
const path = require('path');
const QRCode = require('qrcode');
const { Boom } = require('@hapi/boom');
const {
    default: makeWASocket,
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
    fetchLatestWaWebVersion,
    downloadMediaMessage,
} = require('@whiskeysockets/baileys');

const config = require('./config');
const logger = require('./logger');
const { sendWebhook } = require('./webhook');

/** @type {Map<string, { sock: any, qr: string|null, qrExpiresAt: Date|null, status: string, lastError: string|null }>} */
const sessions = new Map();

function sessionFolder(sessionKey) {
    if (!/^[A-Za-z0-9_\-]{3,128}$/.test(sessionKey)) {
        throw new Error(`Invalid session_key: ${sessionKey}`);
    }
    return path.join(config.sessionsDir, sessionKey);
}

function getSession(sessionKey) {
    return sessions.get(sessionKey) || null;
}

function summarize(state) {
    if (!state) {
        return null;
    }
    const user = state.sock?.user;
    return {
        status: state.status,
        jid: user?.id || null,
        phone_number: user?.id ? user.id.split(':')[0].split('@')[0] : null,
        display_name: user?.name || null,
        qr_available: !!state.qr,
        qr_expires_at: state.qrExpiresAt,
        last_error: state.lastError,
    };
}

// Ask WhatsApp Web which client version it is serving now.
// fetchLatestBaileysVersion() returns Baileys' own record of it, which by Sep 2026
// had fallen ~4 million revisions behind (2.3000.1043857760 vs the live
// 2.3000.1047999808). WhatsApp terminated every new-device registration on the
// stale version -- "Connection Terminated", never a QR -- and accepted the live
// one first time. Falls back to Baileys' record if the live lookup fails, and
// caches only a confirmed-live answer so one bad lookup can't pin a stale version.
const VERSION_TTL_MS = 60 * 60 * 1000;
let versionCache = null;

async function resolveWaVersion() {
    if (versionCache && Date.now() - versionCache.at < VERSION_TTL_MS) {
        return versionCache.version;
    }

    try {
        const live = await fetchLatestWaWebVersion({});

        if (live?.isLatest && Array.isArray(live.version)) {
            versionCache = { version: live.version, at: Date.now() };

            return live.version;
        }

        logger.warn(
            { error: live?.error?.message },
            'Live WhatsApp Web version unavailable; falling back to Baileys record',
        );
    } catch (error) {
        logger.warn(
            { error: error.message },
            'Live WhatsApp Web version lookup failed; falling back to Baileys record',
        );
    }

    const { version } = await fetchLatestBaileysVersion();

    return version;
}

async function startSession(sessionKey, displayLabel = sessionKey) {
    let state = sessions.get(sessionKey);
    if (state && state.status !== 'failed') {
        return summarize(state);
    }

    const folder = sessionFolder(sessionKey);
    fs.mkdirSync(folder, { recursive: true });

    const { state: authState, saveCreds } = await useMultiFileAuthState(folder);
    const version = await resolveWaVersion();

    const sock = makeWASocket({
        version,
        auth: authState,
        printQRInTerminal: false,
        logger: logger.child({ session: sessionKey }),
        browser: ['FitnessCenter', 'Chrome', '1.0.0'],
        syncFullHistory: false,
        markOnlineOnConnect: false,
    });

    state = {
        sock,
        qr: null,
        qrExpiresAt: null,
        status: 'connecting',
        lastError: null,
        label: displayLabel,
    };
    sessions.set(sessionKey, state);

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            try {
                const dataUrl = await QRCode.toDataURL(qr, { margin: 1, scale: 6 });
                const expiresAt = new Date(Date.now() + 60_000);
                state.qr = dataUrl;
                state.qrExpiresAt = expiresAt;
                state.status = 'qr_ready';
                await sendWebhook('session.qr', {
                    session_key: sessionKey,
                    qr: dataUrl,
                    expires_at: expiresAt.toISOString(),
                });
            } catch (err) {
                logger.error({ err, sessionKey }, 'Failed to encode QR');
            }
        }

        if (connection === 'open') {
            state.qr = null;
            state.qrExpiresAt = null;
            state.status = 'connected';
            state.lastError = null;
            const user = sock.user || {};
            const jid = user.id || null;
            const phone = jid ? jid.split(':')[0].split('@')[0] : null;
            await sendWebhook('session.connected', {
                session_key: sessionKey,
                jid,
                phone_number: phone,
                display_name: user.name || null,
            });
        }

        if (connection === 'close') {
            const status = (lastDisconnect?.error instanceof Boom)
                ? lastDisconnect.error.output?.statusCode
                : 0;
            const reason = lastDisconnect?.error?.message || 'unknown';
            state.status = 'disconnected';
            state.lastError = reason;
            await sendWebhook('session.disconnected', { session_key: sessionKey, reason });

            const loggedOut = status === DisconnectReason.loggedOut;
            sessions.delete(sessionKey);

            if (!loggedOut) {
                logger.info({ sessionKey, reason, status }, 'Reconnecting session');
                setTimeout(() => {
                    startSession(sessionKey, displayLabel).catch((err) =>
                        logger.error({ err, sessionKey }, 'Reconnect failed')
                    );
                }, 2_000);
            } else {
                logger.warn({ sessionKey }, 'Session logged out — credentials wiped');
                try {
                    fs.rmSync(folder, { recursive: true, force: true });
                } catch (err) {
                    logger.warn({ err, sessionKey }, 'Failed to wipe credentials');
                }
            }
        }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') {
            return;
        }
        for (const msg of messages) {
            // Skip our own messages and protocol messages
            if (!msg.message || msg.key?.fromMe) {
                continue;
            }
            const chatJid = msg.key?.remoteJid || '';
            if (!shouldIngestChat(chatJid)) {
                logger.debug({ sessionKey, chatJid }, 'Ignoring message from non-ingested chat');
                continue;
            }
            await handleInboundMessage(sessionKey, sock, msg);
        }
    });

    sock.ev.on('messages.update', async (updates) => {
        for (const u of updates) {
            if (!u.update?.status) {
                continue;
            }
            // Baileys numeric status: 1 pending, 2 sent, 3 delivered, 4 read
            const map = { 3: 'delivered', 4: 'read', 5: 'failed' };
            const mapped = map[u.update.status];
            if (!mapped) {
                continue;
            }
            await sendWebhook('message.status', {
                session_key: sessionKey,
                wa_message_id: u.key?.id,
                status: mapped,
            });
        }
    });

    return summarize(state);
}

function chatKind(jid) {
    const value = typeof jid === 'string' ? jid : '';
    if (value === 'status@broadcast') return 'status';
    if (value.endsWith('@g.us')) return 'group';
    if (value.endsWith('@newsletter')) return 'newsletter';
    if (value.endsWith('@broadcast')) return 'broadcast';
    return 'private';
}

/**
 * Whether a chat is forwarded to Laravel at all. Status/newsletter/broadcast
 * traffic is dropped here, before any media is downloaded — this is what keeps
 * the disk from filling up with other people's status videos.
 */
function shouldIngestChat(jid) {
    switch (chatKind(jid)) {
        case 'status': return config.ingest.status;
        case 'newsletter': return config.ingest.newsletters;
        case 'broadcast': return config.ingest.broadcasts;
        case 'group': return config.ingest.groups;
        default: return true;
    }
}

/**
 * WhatsApp reports media size up front. It arrives as a number, a string, or a
 * protobuf Long depending on the message — normalise to bytes so we can refuse
 * the download before spending bandwidth or memory on it.
 */
function declaredMediaSize(node) {
    const raw = node?.fileLength;
    if (raw === null || raw === undefined) return 0;
    if (typeof raw === 'number') return Number.isFinite(raw) ? raw : 0;
    if (typeof raw === 'string') return parseInt(raw, 10) || 0;
    if (typeof raw.toNumber === 'function') {
        try { return raw.toNumber(); } catch { return 0; }
    }
    if (typeof raw.low === 'number') return raw.low;
    return 0;
}

/**
 * Decide whether to pull the bytes for a media message.
 * Returns null to download, or a string reason to skip.
 */
function mediaSkipReason(type, chatJid, size) {
    if (!config.media.downloadTypes.includes(type)) {
        return 'type_not_allowed';
    }
    if (chatKind(chatJid) === 'group' && !config.media.fromGroups) {
        return 'group_media_disabled';
    }
    if (config.media.maxBytes > 0 && size > config.media.maxBytes) {
        return 'too_large';
    }
    return null;
}

async function handleInboundMessage(sessionKey, sock, msg) {
    const m = msg.message;
    const chatJid = msg.key.remoteJid;
    const senderJid = msg.key.participant || chatJid;
    const waMessageId = msg.key.id;

    // Derive a real phone number, not WA's opaque @lid alias.
    // Order of preference:
    //   1. Baileys-provided phone aliases on the message key (senderPn / participantPn).
    //   2. For 1:1 chats, the chat JID itself is `<phone>@s.whatsapp.net`.
    //   3. Otherwise null (UI can map the @lid manually via the inbox).
    const phoneFromJid = (jid) => (typeof jid === 'string' && jid.endsWith('@s.whatsapp.net'))
        ? jid.split('@')[0].split(':')[0]
        : null;
    const senderPhone =
        phoneFromJid(msg.key.senderPn)
        || phoneFromJid(msg.key.participantPn)
        || phoneFromJid(senderJid)
        || phoneFromJid(chatJid)
        || null;

    let type = 'text';
    let content = '';
    let mediaMime = null;
    let mediaFilename = null;
    let mediaUrl = null;
    let mediaNode = null; // baileys media message node, if any

    if (m.conversation) {
        content = m.conversation;
    } else if (m.extendedTextMessage?.text) {
        content = m.extendedTextMessage.text;
    } else if (m.imageMessage) {
        type = 'image';
        content = m.imageMessage.caption || '';
        mediaMime = m.imageMessage.mimetype || 'image/jpeg';
        mediaNode = m.imageMessage;
    } else if (m.videoMessage) {
        type = 'video';
        content = m.videoMessage.caption || '';
        mediaMime = m.videoMessage.mimetype || 'video/mp4';
        mediaNode = m.videoMessage;
    } else if (m.audioMessage) {
        type = 'audio';
        mediaMime = m.audioMessage.mimetype || 'audio/ogg';
        mediaNode = m.audioMessage;
    } else if (m.documentMessage) {
        type = 'document';
        content = m.documentMessage.caption || '';
        mediaMime = m.documentMessage.mimetype || 'application/octet-stream';
        mediaFilename = m.documentMessage.fileName || null;
        mediaNode = m.documentMessage;
    } else if (m.stickerMessage) {
        type = 'sticker';
        mediaMime = 'image/webp';
        mediaNode = m.stickerMessage;
    } else if (m.locationMessage) {
        type = 'location';
        content = `${m.locationMessage.degreesLatitude},${m.locationMessage.degreesLongitude}`;
    } else if (m.contactMessage) {
        type = 'contact';
        content = m.contactMessage.displayName || '';
    }

    // Download inbound media so the front-end can render it — but only when it
    // passes the size/type/chat policy. Skipped media still yields a message
    // row so the inbox shows that something arrived.
    let mediaData = null;
    let mediaSize = null;
    let mediaSkipped = null;

    if (mediaNode) {
        const declaredSize = declaredMediaSize(mediaNode);
        mediaSkipped = mediaSkipReason(type, chatJid, declaredSize);

        if (mediaSkipped) {
            logger.info(
                { sessionKey, type, chatJid, declaredSize, reason: mediaSkipped },
                'Skipped inbound media download'
            );
        } else {
            try {
                const buf = await downloadMediaMessage(msg, 'buffer', {}, { reuploadRequest: sock.updateMediaMessage });
                if (buf && buf.length) {
                    // WhatsApp's declared size can be absent or wrong; re-check
                    // what actually landed before shipping it to Laravel.
                    if (config.media.maxBytes > 0 && buf.length > config.media.maxBytes) {
                        mediaSkipped = 'too_large';
                        logger.info(
                            { sessionKey, type, size: buf.length, reason: mediaSkipped },
                            'Discarded oversized inbound media after download'
                        );
                    } else {
                        mediaData = buf.toString('base64');
                        mediaSize = buf.length;
                    }
                }
            } catch (err) {
                logger.warn({ err: err?.message, sessionKey, type }, 'failed to download inbound media');
                mediaSkipped = 'download_failed';
            }
        }
    }

    await sendWebhook('message.received', {
        session_key: sessionKey,
        wa_message_id: waMessageId,
        chat_jid: chatJid,
        sender_jid: senderJid,
        sender_phone: senderPhone,
        sender_name: msg.pushName || null,
        type,
        content,
        media_url: mediaUrl,
        media_mime: mediaMime,
        media_filename: mediaFilename,
        media_data: mediaData,
        media_size: mediaSize,
        media_skipped: mediaSkipped,
        payload: { keys: Object.keys(m || {}) },
        timestamp: new Date(((Number(msg.messageTimestamp) || 0) * 1000) || Date.now()).toISOString(),
    });
}

async function logout(sessionKey) {
    const state = sessions.get(sessionKey);
    if (state?.sock) {
        try {
            await state.sock.logout();
        } catch (err) {
            logger.warn({ err, sessionKey }, 'logout() raised');
        }
    }
    sessions.delete(sessionKey);
    try {
        fs.rmSync(sessionFolder(sessionKey), { recursive: true, force: true });
    } catch (err) {
        logger.warn({ err, sessionKey }, 'Failed to wipe credentials');
    }
}

function requireConnected(sessionKey) {
    const state = sessions.get(sessionKey);
    if (!state || state.status !== 'connected') {
        const err = new Error(`Session ${sessionKey} is not connected (status=${state?.status || 'none'})`);
        err.statusCode = 409;
        throw err;
    }
    return state;
}

/**
 * Resume every persisted session on disk. Call once at boot. Sessions whose
 * `creds.json` exists will reconnect without a new QR scan.
 */
async function resumeAllSessions() {
    let entries = [];
    try {
        entries = fs.readdirSync(config.sessionsDir, { withFileTypes: true });
    } catch (err) {
        if (err.code !== 'ENOENT') {
            logger.warn({ err }, 'resumeAllSessions: cannot read sessions dir');
        }
        return;
    }

    for (const entry of entries) {
        if (!entry.isDirectory()) continue;
        const key = entry.name;
        if (!/^[A-Za-z0-9_\-]{3,128}$/.test(key)) continue;
        if (!fs.existsSync(path.join(config.sessionsDir, key, 'creds.json'))) continue;

        try {
            await startSession(key, key);
            logger.info({ sessionKey: key }, 'Resumed session from disk');
        } catch (err) {
            logger.warn({ err, sessionKey: key }, 'Failed to resume session');
        }
    }
}

/**
 * If the in-memory socket is missing but credentials exist on disk, transparently
 * resume before retrying the caller's intended action.
 */
async function ensureStarted(sessionKey) {
    const state = sessions.get(sessionKey);
    if (state) return state;
    if (fs.existsSync(path.join(sessionFolder(sessionKey), 'creds.json'))) {
        await startSession(sessionKey, sessionKey);
        // Wait briefly for connection.update → 'open'
        for (let i = 0; i < 30; i += 1) {
            const s = sessions.get(sessionKey);
            if (s?.status === 'connected') return s;
            await new Promise((r) => setTimeout(r, 200));
        }
    }
    return sessions.get(sessionKey) || null;
}

async function sendText(sessionKey, to, text) {
    await ensureStarted(sessionKey);
    const { sock } = requireConnected(sessionKey);
    const result = await sock.sendMessage(to, { text });
    return { wa_message_id: result?.key?.id || null };
}

async function sendMedia(sessionKey, to, type, source, extras = {}) {
    await ensureStarted(sessionKey);
    const { sock } = requireConnected(sessionKey);
    const payload = {};
    // `source` is either { url } or { data: <base64> }
    const buffer = source && source.data
        ? Buffer.from(source.data, 'base64')
        : { url: source?.url };

    switch (type) {
        case 'image':
            payload.image = buffer;
            payload.caption = extras.caption;
            break;
        case 'video':
            payload.video = buffer;
            payload.caption = extras.caption;
            break;
        case 'audio':
            payload.audio = buffer;
            payload.mimetype = extras.mimetype || 'audio/ogg; codecs=opus';
            payload.ptt = !!extras.ptt;
            break;
        case 'document':
            payload.document = buffer;
            payload.mimetype = extras.mimetype || 'application/octet-stream';
            payload.fileName = extras.filename || 'file';
            break;
        case 'sticker':
            payload.sticker = buffer;
            break;
        default:
            throw Object.assign(new Error(`Unsupported media type: ${type}`), { statusCode: 400 });
    }

    const result = await sock.sendMessage(to, payload);
    return { wa_message_id: result?.key?.id || null };
}

async function sendStatus(sessionKey, payload) {
    const { sock } = requireConnected(sessionKey);
    const statusJid = 'status@broadcast';
    const opts = { statusJidList: [sock.user?.id].filter(Boolean) };

    if (payload.type === 'image' && payload.url) {
        const r = await sock.sendMessage(statusJid, { image: { url: payload.url }, caption: payload.caption || '' }, opts);
        return { wa_message_id: r?.key?.id || null };
    }
    if (payload.type === 'video' && payload.url) {
        const r = await sock.sendMessage(statusJid, { video: { url: payload.url }, caption: payload.caption || '' }, opts);
        return { wa_message_id: r?.key?.id || null };
    }
    const r = await sock.sendMessage(statusJid, {
        text: payload.text || '',
        backgroundColor: payload.background_color,
    }, opts);
    return { wa_message_id: r?.key?.id || null };
}

async function listGroups(sessionKey) {
    const { sock } = requireConnected(sessionKey);
    const groups = await sock.groupFetchAllParticipating();
    return Object.values(groups).map((g) => ({
        jid: g.id,
        name: g.subject,
        size: g.participants?.length || 0,
    }));
}

async function listChannels(sessionKey) {
    const { sock } = requireConnected(sessionKey);
    if (typeof sock.newsletterFetchSubscribed !== 'function') {
        return [];
    }
    try {
        const list = await sock.newsletterFetchSubscribed();
        return (list || []).map((c) => ({ jid: c.id, name: c.name || c.id }));
    } catch (err) {
        logger.warn({ err, sessionKey }, 'Failed to list channels');
        return [];
    }
}

module.exports = {
    startSession,
    resumeAllSessions,
    ensureStarted,
    getSession,
    summarize,
    logout,
    sendText,
    sendMedia,
    sendStatus,
    listGroups,
    listChannels,
};
