#!/usr/bin/env node
'use strict';

// Move paired sessions from this bridge's session files to Chatway Gateway, so a
// client keeps its WhatsApp link without re-scanning a QR.
//
// Run from the bridge directory, with the bridge STOPPED:
//
//   node bin/export-to-gateway.js --all --dry-run
//   node bin/export-to-gateway.js --all --activate
//   node bin/export-to-gateway.js --session shop-2-0wncqMMbjg
//
// Reads WA_GATEWAY_URL and WA_GATEWAY_IMPORT_TOKEN from the environment or .env.
// The token needs sessions:import (plus sessions:write for --activate): mint it
// for the migration and revoke it once every session has moved.

const fs = require('fs');
const path = require('path');
const config = require('../src/config');

// Every category this Baileys version writes. Longest first, so that
// 'sender-key-memory' is never mistaken for 'sender-key'.
const CATEGORIES = [
    'app-state-sync-version',
    'app-state-sync-key',
    'sender-key-memory',
    'sender-key',
    'pre-key',
    'session',
];

// useMultiFileAuthState's own rule, verbatim, for the round-trip check below.
const fixFileName = (file) => file.replace(/\//g, '__').replace(/:/g, '-');

/**
 * Recover (category, id) from a key file's name, or null if that can't be done
 * with certainty.
 *
 * Baileys' fixFileName isn't reversible in general: it rewrites ':' as '-', and
 * some ids contain a real '-' -- old-style group JIDs look like
 * 254702006545-1515590128@g.us. Guessing wrong files a key under an id Baileys
 * never asks for, and that chat then fails to decrypt. So:
 *  - only sender-key ids contain ':', always as the '::' separator, so only
 *    there is '--' turned back into '::'; a lone '-' is left alone;
 *  - in pre-key, session and app-state ids a '-' can only be a rewritten ':',
 *    which can't be recovered safely, so the file is refused instead;
 *  - every result must reproduce the original name through fixFileName.
 */
function parseKeyFile(file) {
    if (!file.endsWith('.json') || file === 'creds.json') return null;

    const base = file.slice(0, -'.json'.length);
    const category = CATEGORIES.find((c) => base.startsWith(`${c}-`));
    if (!category) return null;

    let id = base.slice(category.length + 1).replace(/__/g, '/');

    if (category === 'sender-key') {
        id = id.replace(/--/g, '::');
    } else if (category !== 'sender-key-memory' && id.includes('-')) {
        return null;
    }

    return fixFileName(`${category}-${id}.json`) === file ? { category, id } : null;
}

/**
 * Read one session directory into the stored shape the gateway imports: raw
 * JSON exactly as useMultiFileAuthState wrote it, Buffers still encoded, so the
 * gateway revives it with the same BufferJSON rules Baileys uses.
 */
function readSession(dir) {
    const credsPath = path.join(dir, 'creds.json');
    if (!fs.existsSync(credsPath)) return { skip: 'no creds.json' };

    const creds = JSON.parse(fs.readFileSync(credsPath, 'utf8'));
    if (typeof creds?.me?.id !== 'string' || creds.me.id === '') return { skip: 'not paired' };

    const keys = {};
    const refused = [];
    let unreadable = 0;
    let count = 0;

    for (const file of fs.readdirSync(dir)) {
        if (file === 'creds.json' || !file.endsWith('.json')) continue;

        const parsed = parseKeyFile(file);
        if (!parsed) {
            refused.push(file);
            continue;
        }

        let value;
        try {
            value = JSON.parse(fs.readFileSync(path.join(dir, file), 'utf8'));
        } catch {
            // Baileys' own readData treats an unreadable file as a missing key,
            // so the bridge already runs without it. Import the same way, but say so.
            unreadable += 1;
            continue;
        }
        if (!value) continue;

        (keys[parsed.category] ??= {})[parsed.id] = value;
        count += 1;
    }

    return { authState: { creds, keys }, count, refused, unreadable };
}

function parseArgs(argv) {
    const opts = { sessions: [], all: false, dryRun: false, activate: false, force: false };
    for (let i = 0; i < argv.length; i += 1) {
        const a = argv[i];
        if (a === '--session') opts.sessions.push(argv[++i]);
        else if (a.startsWith('--session=')) opts.sessions.push(a.slice('--session='.length));
        else if (a === '--all') opts.all = true;
        else if (a === '--dry-run') opts.dryRun = true;
        else if (a === '--activate') opts.activate = true;
        else if (a === '--force') opts.force = true;
        else throw new Error(`Unknown option: ${a}`);
    }
    return opts;
}

async function bridgeIsRunning() {
    try {
        const res = await fetch(`http://${config.host}:${config.port}/health`, { signal: AbortSignal.timeout(5000) });
        return res.ok && (await res.json().catch(() => ({}))).ok === true;
    } catch {
        return false; // unreachable is the answer we want: the bridge is stopped
    }
}

async function gateway(method, url, token, body) {
    const res = await fetch(url, {
        method,
        headers: { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
        signal: AbortSignal.timeout(60_000),
    });
    return { status: res.status, ok: res.ok, json: await res.json().catch(() => ({})) };
}

async function main() {
    const opts = parseArgs(process.argv.slice(2));

    if ((opts.sessions.length > 0) === opts.all) {
        throw new Error('Choose exactly one of --session or --all.');
    }

    const gatewayUrl = String(process.env.WA_GATEWAY_URL ?? '').replace(/\/+$/, '');
    const token = String(process.env.WA_GATEWAY_IMPORT_TOKEN ?? '');

    if (!opts.dryRun) {
        if (!gatewayUrl || !token) throw new Error('Set WA_GATEWAY_URL and WA_GATEWAY_IMPORT_TOKEN before moving sessions.');

        // SECURITY: the request body is a paired device's WhatsApp credentials.
        const { protocol, hostname } = new URL(gatewayUrl);
        if (protocol !== 'https:' && !['127.0.0.1', 'localhost', '[::1]'].includes(hostname)) {
            throw new Error('WA_GATEWAY_URL must be https:// -- this sends WhatsApp credentials.');
        }

        if (!opts.force && await bridgeIsRunning()) {
            throw new Error(
                `The bridge is still running on ${config.host}:${config.port}. Stop it first: every message it ` +
                'processes advances each session\'s encryption state, so a copy taken while it runs is already ' +
                'stale, and a session booted from a stale copy fails to decrypt incoming messages.',
            );
        }

        const ready = await fetch(`${gatewayUrl}/ready`, { signal: AbortSignal.timeout(10_000) })
            .then((r) => r.json()).catch(() => ({}));
        if (ready.ok !== true) throw new Error(`Chatway Gateway at ${gatewayUrl} is not ready.`);
    }

    const root = config.sessionsDir;
    const available = fs.existsSync(root)
        ? fs.readdirSync(root, { withFileTypes: true }).filter((d) => d.isDirectory()).map((d) => d.name)
        : [];
    const chosen = opts.all ? available : opts.sessions;

    const rows = [];
    let failures = 0;

    for (const key of chosen) {
        // An operator can type anything into --session; keep it inside sessionsDir.
        const dir = path.resolve(root, key);
        if (!/^[A-Za-z0-9._-]+$/.test(key) || path.dirname(dir) !== root || !available.includes(key)) {
            rows.push([key, 'not found']);
            failures += 1;
            continue;
        }

        let session;
        try {
            session = readSession(dir);
        } catch (error) {
            rows.push([key, `failed: could not read session (${error.message})`]);
            failures += 1;
            continue;
        }

        if (session.skip) {
            rows.push([key, `skipped: ${session.skip}`]);
            continue;
        }

        if (session.refused.length) {
            rows.push([key, `refused: ${session.refused.length} key file(s) with names that can't be mapped back safely`]);
            failures += 1;
            continue;
        }

        const note = session.unreadable ? `, ${session.unreadable} unreadable skipped` : '';

        if (opts.dryRun) {
            rows.push([key, `would move (${session.count} keys${note})`]);
            continue;
        }

        let res;
        try {
            // SECURITY: credentials go only to the gateway, and are never printed or logged.
            res = await gateway('PUT', `${gatewayUrl}/sessions/${encodeURIComponent(key)}/auth-state`, token, { auth_state: session.authState });
        } catch (error) {
            rows.push([key, `failed: ${error.message}`]);
            failures += 1;
            continue;
        }

        if (res.status === 409) {
            rows.push([key, 'skipped: already active on the gateway']);
            continue;
        }
        if (!res.ok) {
            rows.push([key, `failed: ${res.json.error ?? `HTTP ${res.status}`}`]);
            failures += 1;
            continue;
        }

        if (!opts.activate) {
            rows.push([key, `imported (${session.count} keys${note})`]);
            continue;
        }

        try {
            const start = await gateway('POST', `${gatewayUrl}/sessions`, token, { session_key: key });
            if (start.ok) {
                rows.push([key, `imported (${session.count} keys${note}), activated`]);
            } else {
                rows.push([key, `imported (${session.count} keys${note}); activation failed: ${start.json.error ?? `HTTP ${start.status}`}`]);
                failures += 1;
            }
        } catch (error) {
            rows.push([key, `imported (${session.count} keys${note}); activation failed: ${error.message}`]);
            failures += 1;
        }
    }

    if (!rows.length) {
        console.log('No sessions found.');
        return 0;
    }

    const width = Math.max(...rows.map((r) => r[0].length), 7);
    console.log(`${'Session'.padEnd(width)}  Result`);
    for (const [key, result] of rows) console.log(`${key.padEnd(width)}  ${result}`);
    console.log(failures
        ? `\n${failures} session(s) did not move. Nothing else was affected; fix the cause and re-run for those.`
        : `\n${opts.dryRun ? 'Dry run: nothing was sent.' : 'Done.'}`);

    return failures ? 1 : 0;
}

if (require.main === module) {
    main().then((code) => process.exit(code)).catch((error) => {
        console.error(error.message);
        process.exit(1);
    });
}

module.exports = { parseKeyFile, readSession, fixFileName };
