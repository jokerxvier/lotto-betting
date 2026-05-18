/**
 * PCSO scraper sidecar. Express service bound to 127.0.0.1 only — the
 * Laravel app on the same box hits us via Http::get('http://127.0.0.1:8787/scrape').
 *
 * Auth: every request must carry `X-Scraper-Token: <secret>` matching the
 * SCRAPER_TOKEN env var. Defense-in-depth against a bug that accidentally
 * binds us to 0.0.0.0; the loopback bind is the real boundary.
 *
 * Routes:
 *   GET /health           → { ok: true }
 *   GET /scrape?refresh=1 → { source, fetchedAt, rows: [...] }
 */

import express from 'express';
import { getLatestResults, shutdown } from './browser.mjs';

const PORT = parseInt(process.env.SCRAPER_PORT ?? '8787', 10);
const HOST = process.env.SCRAPER_HOST ?? '127.0.0.1';
const TOKEN = process.env.SCRAPER_TOKEN ?? '';

if (TOKEN === '') {
    console.error('FATAL: SCRAPER_TOKEN env var is required');
    process.exit(1);
}

const app = express();

app.use((req, res, next) => {
    if (req.path === '/health') {
return next();
}

    if (req.header('x-scraper-token') !== TOKEN) {
        return res.status(401).json({ error: 'unauthorized' });
    }

    next();
});

app.get('/health', (_req, res) => {
    res.json({ ok: true, uptime: process.uptime() });
});

app.get('/scrape', async (req, res) => {
    try {
        const refresh = req.query.refresh === '1';
        const { fetchedAt, rows } = await getLatestResults({ refresh });
        res.json({ source: 'pcso.gov.ph', fetchedAt, rows });
    } catch (err) {
        const message = err instanceof Error ? err.message : String(err);
        console.error(`[scrape] ${message}`);
        res.status(502).json({ error: 'upstream_failure', detail: message });
    }
});

const server = app.listen(PORT, HOST, () => {
    console.log(`scraper listening on http://${HOST}:${PORT}`);
});

async function bye(signal) {
    console.log(`received ${signal}, shutting down`);
    server.close();
    await shutdown();
    process.exit(0);
}

process.on('SIGTERM', () => bye('SIGTERM'));
process.on('SIGINT', () => bye('SIGINT'));
