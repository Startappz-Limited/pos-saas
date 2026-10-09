'use strict';

const crypto = require('crypto');
const axios = require('axios');
const config = require('./config');
const logger = require('./logger');

function bodyLimit() {
    return Math.max(2 * 1024 * 1024, Math.ceil(config.media.maxBytes * 1.5) + 1024 * 1024);
}

/**
 * POST a payload to Laravel with an HMAC-SHA256 signature header
 * computed over the exact JSON body bytes.
 */
async function sendWebhook(event, data) {
    const body = JSON.stringify({ event, data });
    const signature = crypto
        .createHmac('sha256', config.webhookSecret)
        .update(body)
        .digest('hex');

    try {
        await axios.post(config.webhookUrl, body, {
            headers: {
                'Content-Type': 'application/json',
                'X-Baileys-Signature': signature,
            },
            timeout: 30_000,
            // Base64 inflates payloads ~33%; allow headroom over the media cap
            // rather than the old flat 64MB, which let a single forwarded video
            // through regardless of policy.
            maxBodyLength: bodyLimit(),
            maxContentLength: bodyLimit(),
        });
    } catch (err) {
        logger.warn(
            { event, sessionKey: data?.session_key, status: err.response?.status, message: err.message },
            'Webhook delivery failed'
        );
    }
}

module.exports = { sendWebhook };
