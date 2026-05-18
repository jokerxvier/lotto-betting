/**
 * Draw-time labels matching the PCSO mainstream presentation:
 *   slotLabel("...T06:00:00Z") → "2PM"   (when 14:00 Manila — canonical slot)
 *   slotLabel("...T07:30:00Z") → "3:30 PM" (non-canonical → graceful fallback)
 *
 *   formatDrawRow(...)         → "5PM - MON MAY 18"
 *
 * Both render in Asia/Manila via `toLocaleString('en-PH', { timeZone: ... })`
 * so a user's browser TZ doesn't shift PCSO labels off-slot.
 */

const TZ = 'Asia/Manila';

const CANONICAL_SLOTS: Record<number, string> = {
    14: '2PM',
    17: '5PM',
    21: '9PM',
};

/**
 * Returns the PCSO slot label (`2PM`, `5PM`, `9PM`) when the draw's hour
 * in Manila matches a canonical slot. Falls back to a short clock format
 * (`g:i A` style, e.g. `4:30 PM`) for any non-canonical draw so the label
 * never reads "undefined" or breaks the layout.
 */
export function slotLabel(iso: string): string {
    const d = new Date(iso);
    // Manila hour-of-day, 0-23.
    const hour = Number.parseInt(
        d.toLocaleString('en-PH', {
            timeZone: TZ,
            hour: '2-digit',
            hour12: false,
        }),
        10,
    );

    const minute = Number.parseInt(
        d.toLocaleString('en-PH', {
            timeZone: TZ,
            minute: '2-digit',
        }),
        10,
    );

    if (minute === 0 && CANONICAL_SLOTS[hour] !== undefined) {
        return CANONICAL_SLOTS[hour];
    }

    return d.toLocaleTimeString('en-PH', {
        timeZone: TZ,
        hour: 'numeric',
        minute: '2-digit',
    });
}

/**
 * "5PM - MON MAY 18" — slot first, then weekday + month + day in uppercase
 * so the row reads at a glance. Used by GameCard's draw row and the
 * SelectDrawSheet's per-row buttons.
 */
export function formatDrawRow(iso: string): string {
    const d = new Date(iso);
    const slot = slotLabel(iso);
    const datePart = d
        .toLocaleDateString('en-PH', {
            timeZone: TZ,
            weekday: 'short',
            month: 'short',
            day: 'numeric',
        })
        .toUpperCase()
        .replace(/,/g, '');

    return `${slot} - ${datePart}`;
}
