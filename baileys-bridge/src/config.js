'use strict';

require('dotenv').config();

const path = require('path');

function required(name) {
    const value = process.env[name];
    if (!value || value.trim() === '') {
        throw new Error(`Missing required env var ${name}`);
    }
    return value;
}

function bool(name, fallback) {
    const value = process.env[name];
    if (value === undefined || value.trim() === '') {
        return fallback;
    }
    return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
}

function int(name, fallback) {
    const value = parseInt(process.env[name] || '', 10);
    return Number.isFinite(value) && value >= 0 ? value : fallback;
}

function list(name, fallback) {
    const value = process.env[name];
    if (!value || value.trim() === '') {
        return fallback;
    }
    return value.split(',').map((v) => v.trim().toLowerCase()).filter(Boolean);
}

module.exports = {
    port: parseInt(process.env.PORT || '3025', 10),
    host: process.env.BIND_HOST || '127.0.0.1',
    bridgeToken: required('BRIDGE_TOKEN'),
    webhookUrl: required('LARAVEL_WEBHOOK_URL'),
    webhookSecret: required('LARAVEL_WEBHOOK_SECRET'),
    sessionsDir: path.resolve(process.env.SESSIONS_DIR || './sessions'),
    logLevel: process.env.LOG_LEVEL || 'info',

    /*
     * Which inbound chats are forwarded to Laravel at all. Status updates,
     * channels and broadcast lists are pure noise for a POS inbox and are the
     * main driver of runaway disk usage (every contact's daily status video
     * would otherwise be downloaded and stored forever).
     */
    ingest: {
        status: bool('INGEST_STATUS', false),
        newsletters: bool('INGEST_NEWSLETTERS', false),
        broadcasts: bool('INGEST_BROADCASTS', false),
        groups: bool('INGEST_GROUPS', true),
    },

    /*
     * Media download policy. A message that fails these checks is still
     * forwarded (so the inbox shows it happened) — only the bytes are skipped.
     */
    media: {
        // Anything larger than this is never downloaded.
        maxBytes: int('MEDIA_MAX_BYTES', 5 * 1024 * 1024),
        // Message types whose bytes we actually download. Video and sticker are
        // off by default: video is the single biggest disk consumer and
        // stickers are high-volume noise.
        downloadTypes: list('MEDIA_DOWNLOAD_TYPES', ['image', 'audio', 'document']),
        // Download media sent inside group chats.
        fromGroups: bool('MEDIA_FROM_GROUPS', false),
    },
};
