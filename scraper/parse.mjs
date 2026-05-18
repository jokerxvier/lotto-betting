/**
 * Parser for pcso.gov.ph/searchlottoresult.aspx — ASP.NET GridView with five
 * columns: LOTTO GAME | COMBINATIONS | DRAW DATE | JACKPOT | WINNERS.
 *
 * Runs inside `page.evaluate()` (DOM available) OR can take a string of HTML
 * for unit tests (we lift it out via a tiny JSDOM-free regex when in Node).
 * Keep both paths trivially small.
 */

const GAME_ROW_PATTERN = /^(2D|3D|4D|6D)\s+LOTTO(?:\s+(\d{1,2}PM))?$/i;

/**
 * Run inside the browser context via `page.evaluate(parseGridDom)`.
 * Returns rows as plain JSON-safe objects.
 *
 * @returns {{game:string, date:string, numbers:number[], jackpot:string, winners:number}[]}
 */
export function parseGridDom() {
    const grid = document.querySelector('#cphContainer_cpContent_GridView1');
    if (!grid) return [];

    const rows = [];
    for (const tr of grid.querySelectorAll('tr')) {
        const tds = tr.querySelectorAll('td');
        if (tds.length < 5) continue; // header row uses <th>, gets skipped

        const game = tds[0].textContent.trim();
        const combinations = tds[1].textContent.trim();
        const date = tds[2].textContent.trim();
        const jackpot = tds[3].textContent.trim();
        const winners = parseInt(tds[4].textContent.trim(), 10);

        const numbers = combinations
            .split(/\s*-\s*/)
            .map((n) => parseInt(n, 10))
            .filter((n) => Number.isFinite(n));

        if (numbers.length === 0) continue;

        rows.push({ game, date, numbers, jackpot, winners: Number.isFinite(winners) ? winners : null });
    }
    return rows;
}

/**
 * Server-side fallback for tests / debugging. Takes raw HTML string.
 * Same shape as parseGridDom().
 */
export function parseGridHtml(html) {
    const tableMatch = html.match(/<table[^>]*id=["']cphContainer_cpContent_GridView1["'][^>]*>([\s\S]*?)<\/table>/i);
    if (!tableMatch) return [];

    const rows = [];
    const rowParts = tableMatch[1].split(/<\/tr>/i);
    for (const part of rowParts) {
        const cellMatches = [...part.matchAll(/<td[^>]*>([\s\S]*?)<\/td>/gi)];
        if (cellMatches.length < 5) continue;

        const cells = cellMatches.map((m) => m[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim());
        const [game, combinations, date, jackpot, winnersText] = cells;

        const numbers = combinations
            .split(/\s*-\s*/)
            .map((n) => parseInt(n, 10))
            .filter((n) => Number.isFinite(n));

        if (numbers.length === 0) continue;
        if (!GAME_ROW_PATTERN.test(game) && !/lotto/i.test(game)) continue;

        const winners = parseInt(winnersText, 10);
        rows.push({ game, date, numbers, jackpot, winners: Number.isFinite(winners) ? winners : null });
    }
    return rows;
}
