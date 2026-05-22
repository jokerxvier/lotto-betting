/**
 * Compact relative-time formatter for the admin ledger.
 * Returns "just now", "5m ago", "3h ago", "2d ago", or falls back to an
 * absolute short date for anything older than ~30 days. Tuned for dense
 * tables where a short phrase reads faster than a full timestamp.
 */
export function relativeTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    const then = new Date(iso).getTime();

    if (Number.isNaN(then)) {
        return '—';
    }

    const diffMs = Date.now() - then;
    const diffSec = Math.round(diffMs / 1000);

    if (diffSec < 30) {
        return 'just now';
    }

    if (diffSec < 60) {
        return `${diffSec}s ago`;
    }

    const diffMin = Math.round(diffSec / 60);

    if (diffMin < 60) {
        return `${diffMin}m ago`;
    }

    const diffHr = Math.round(diffMin / 60);

    if (diffHr < 24) {
        return `${diffHr}h ago`;
    }

    const diffDay = Math.round(diffHr / 24);

    if (diffDay < 30) {
        return `${diffDay}d ago`;
    }

    return new Date(iso).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Absolute datetime for tooltip / accessible label.
 */
export function absoluteDateTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleString('en-PH', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

/**
 * Buckets a timestamp into Today / Yesterday / This week / Earlier so the
 * transaction ledger can group rows by chronological proximity.
 */
export function dateBucket(iso: string | null): string {
    if (iso === null) {
        return 'Earlier';
    }

    const then = new Date(iso);
    const now = new Date();

    const sameDay =
        then.getFullYear() === now.getFullYear() &&
        then.getMonth() === now.getMonth() &&
        then.getDate() === now.getDate();

    if (sameDay) {
        return 'Today';
    }

    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);

    if (
        then.getFullYear() === yesterday.getFullYear() &&
        then.getMonth() === yesterday.getMonth() &&
        then.getDate() === yesterday.getDate()
    ) {
        return 'Yesterday';
    }

    const sevenDaysAgo = new Date(now);
    sevenDaysAgo.setDate(now.getDate() - 7);

    if (then >= sevenDaysAgo) {
        return 'This week';
    }

    return 'Earlier';
}
