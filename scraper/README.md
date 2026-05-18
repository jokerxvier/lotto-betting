# PCSO scraper sidecar

Tiny Node + Playwright + Express service that scrapes
`pcso.gov.ph/searchlottoresult.aspx` and returns parsed JSON to the Laravel app.

Lives outside the main app's `package.json` so its Chromium dependency
doesn't bloat the Vite bundle.

## Local dev

```bash
cd scraper
npm install                          # ~50 MB node_modules
npx playwright install chromium      # ~300 MB Chromium binary

# Run the daemon (port 8787 by default, 127.0.0.1 only)
SCRAPER_TOKEN=dev npm start
```

Then from another shell:

```bash
curl -s -H 'X-Scraper-Token: dev' http://127.0.0.1:8787/health
# {"ok":true,"uptime":1.2}

curl -s -H 'X-Scraper-Token: dev' http://127.0.0.1:8787/scrape | jq '.rows[:3]'
# [{"game":"Ultra Lotto 6/58","date":"5/17/2026","numbers":[39,37,35,45,16,52],...}, ...]
```

Force a fresh fetch (bypass the 60s in-memory cache):

```bash
curl -s -H 'X-Scraper-Token: dev' 'http://127.0.0.1:8787/scrape?refresh=1' | jq '.fetchedAt'
```

Smoke-test the browser path without standing up the HTTP layer:

```bash
SCRAPER_TOKEN=dev npm run smoke
```

## Forge deployment

1. Deploy hook (append):
   ```bash
   cd $FORGE_SITE_PATH/scraper
   npm ci
   npx playwright install chromium --with-deps
   ```
2. Sites → Daemons → New daemon:
   - **Command**: `node /home/forge/lotto.app/scraper/server.mjs`
   - **User**: `forge`
   - **Directory**: `/home/forge/lotto.app/scraper`
   - **Environment**: `SCRAPER_TOKEN=<generated>`, `SCRAPER_PORT=8787`
3. Laravel `.env`:
   ```
   LOTTO_SCRAPER_SOURCE=pcso_gov
   LOTTO_SCRAPER_FETCHER=playwright
   LOTTO_SCRAPER_SIDECAR_URL=http://127.0.0.1:8787
   LOTTO_SCRAPER_SIDECAR_TOKEN=<same as above>
   ```
4. `php artisan config:cache && php artisan queue:restart`.
5. Verify: admin scrape button → check `storage/logs/audit-*.log` for
   `admin.draws.scrape` with `settled_count > 0`.

## Files

| File | Purpose |
|---|---|
| `server.mjs` | Express daemon + auth middleware + graceful shutdown |
| `browser.mjs` | Long-lived Chromium singleton + 60s in-memory cache |
| `parse.mjs` | Two parsers: `parseGridDom()` (in-browser) + `parseGridHtml()` (Node-side) |

## Why this exists

- `pcso.gov.ph` sits behind Akamai's bot-WAF. Plain `Http::get()` returns
  HTTP 403 — only a real browser fetch makes it through.
- The previous source `lottopcso.com` is DNS-redirected to
  `blocked.sbmd.cicc.gov.ph` (PH government anti-illegal-gambling block).
- A long-lived sidecar with a warm Chromium is ~200ms per scrape vs.
  ~3–5s for cold launches. The cache further drops the steady-state cost.
- Laravel-side stays simple: one `Http::get()` to localhost.
