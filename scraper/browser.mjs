/**
 * Long-lived Chromium singleton. ONE browser, ONE context, ONE page —
 * lazily launched on first call, reused across `getLatestResults()` calls.
 * The pcso.gov.ph GridView is rendered on initial paint (no JS hydration),
 * so we can read the DOM right after `networkidle`.
 *
 * On any failure we close the page (not the whole browser) and recreate
 * it on the next call. This recovers from zombie page contexts without
 * paying the cold-launch tax every time.
 *
 * Output is cached in-memory for CACHE_TTL_MS — the cron / admin button
 * may fire several times in a row and there's no point re-scraping
 * within a one-minute window. Pass `refresh: true` to bypass.
 */

import { chromium } from 'playwright';
import { parseGridDom } from './parse.mjs';

const TARGET_URL = 'https://www.pcso.gov.ph/searchlottoresult.aspx';
const NAV_TIMEOUT_MS = 30_000;
const SELECTOR_TIMEOUT_MS = 10_000;
const CACHE_TTL_MS = 60_000;

let browser = null;
let context = null;
let page = null;
let cache = { fetchedAt: 0, rows: null };

async function ensurePage() {
    if (browser && context && page && !page.isClosed()) {
        return page;
    }

    if (!browser) {
        browser = await chromium.launch({ headless: true });
    }

    // Plausible client fingerprint: real-ish UA, PH locale + timezone.
    context = await browser.newContext({
        userAgent:
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) ' +
            'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        locale: 'en-PH',
        timezoneId: 'Asia/Manila',
        viewport: { width: 1280, height: 800 },
    });

    page = await context.newPage();

    return page;
}

async function scrapeOnce() {
    const p = await ensurePage();

    try {
        await p.goto(TARGET_URL, { waitUntil: 'networkidle', timeout: NAV_TIMEOUT_MS });
        await p.waitForSelector('#cphContainer_cpContent_GridView1', {
            timeout: SELECTOR_TIMEOUT_MS,
        });

        return await p.evaluate(parseGridDom);
    } catch (err) {
        // Burn the page so the next call gets a fresh one. Keep browser alive.
        try {
            await page?.close();
        } catch {
            /* swallow */
        }

        page = null;

        throw err;
    }
}

/**
 * @param {{ refresh?: boolean }} opts
 * @returns {Promise<{ fetchedAt: string, rows: any[] }>}
 */
export async function getLatestResults({ refresh = false } = {}) {
    const now = Date.now();

    if (!refresh && cache.rows && now - cache.fetchedAt < CACHE_TTL_MS) {
        return { fetchedAt: new Date(cache.fetchedAt).toISOString(), rows: cache.rows };
    }

    const rows = await scrapeOnce();
    cache = { fetchedAt: now, rows };

    return { fetchedAt: new Date(now).toISOString(), rows };
}

export async function shutdown() {
    try {
        await page?.close();
    } catch {
        /* swallow */
    }

    try {
        await context?.close();
    } catch {
        /* swallow */
    }

    try {
        await browser?.close();
    } catch {
        /* swallow */
    }

    page = null;
    context = null;
    browser = null;
    cache = { fetchedAt: 0, rows: null };
}
