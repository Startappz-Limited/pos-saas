'use strict';

const express = require('express');
const config = require('./config');
const logger = require('./logger');
const sm = require('./sessionManager');

const app = express();
app.use(express.json({ limit: '64mb' }));

// Bearer auth
app.use((req, res, next) => {
    if (req.path === '/health') {
        return next();
    }
    const header = req.get('Authorization') || '';
    if (header !== `Bearer ${config.bridgeToken}`) {
        return res.status(401).json({ ok: false, error: 'Unauthorized' });
    }
    next();
});

const wrap = (fn) => (req, res) => {
    Promise.resolve(fn(req, res)).catch((err) => {
        const status = err.statusCode || 500;
        logger.error({ err, path: req.path }, 'Request failed');
        res.status(status).json({ ok: false, error: err.message });
    });
};

app.get('/health', (req, res) => res.json({ ok: true, time: new Date().toISOString() }));

// ---- Sessions
app.post('/sessions', wrap(async (req, res) => {
    const { session_key: key, name } = req.body || {};
    if (!key) {
        return res.status(422).json({ ok: false, error: 'session_key required' });
    }
    const summary = await sm.startSession(key, name || key);
    res.json({ ok: true, ...summary });
}));

app.get('/sessions/:key', wrap(async (req, res) => {
    const state = sm.getSession(req.params.key);
    if (!state) {
        return res.status(404).json({ ok: false, error: 'Session not found' });
    }
    res.json({ ok: true, ...sm.summarize(state) });
}));

app.get('/sessions/:key/qr', wrap(async (req, res) => {
    const state = sm.getSession(req.params.key);
    if (!state || !state.qr) {
        return res.status(404).json({ ok: false, error: 'No QR available' });
    }
    res.json({ ok: true, qr: state.qr, expires_at: state.qrExpiresAt });
}));

app.delete('/sessions/:key', wrap(async (req, res) => {
    await sm.logout(req.params.key);
    res.json({ ok: true });
}));

// ---- Messaging
app.post('/sessions/:key/messages/text', wrap(async (req, res) => {
    const { to, text } = req.body || {};
    if (!to || typeof text !== 'string') {
        return res.status(422).json({ ok: false, error: 'to and text are required' });
    }
    const r = await sm.sendText(req.params.key, to, text);
    res.json({ ok: true, ...r });
}));

app.post('/sessions/:key/messages/media', wrap(async (req, res) => {
    const { to, type, url, data, ...extras } = req.body || {};
    if (!to || !type || (!url && !data)) {
        return res.status(422).json({ ok: false, error: 'to, type and (url or data) are required' });
    }
    const source = data ? { data } : { url };
    const r = await sm.sendMedia(req.params.key, to, type, source, extras);
    res.json({ ok: true, ...r });
}));

app.post('/sessions/:key/status', wrap(async (req, res) => {
    const r = await sm.sendStatus(req.params.key, req.body || {});
    res.json({ ok: true, ...r });
}));

// ---- Discovery
app.get('/sessions/:key/groups', wrap(async (req, res) => {
    const groups = await sm.listGroups(req.params.key);
    res.json(groups);
}));

app.get('/sessions/:key/channels', wrap(async (req, res) => {
    const channels = await sm.listChannels(req.params.key);
    res.json(channels);
}));

// ---- Boot
app.listen(config.port, config.host, () => {
    logger.info(
        { host: config.host, port: config.port, webhook: config.webhookUrl, sessionsDir: config.sessionsDir },
        'Baileys bridge listening'
    );
    // Auto-resume any sessions persisted on disk so a bridge restart doesn't
    // require re-scanning the QR.
    sm.resumeAllSessions().catch((err) => logger.error({ err }, 'resumeAllSessions failed'));
});

process.on('unhandledRejection', (err) => logger.error({ err }, 'unhandledRejection'));
process.on('uncaughtException', (err) => logger.error({ err }, 'uncaughtException'));
