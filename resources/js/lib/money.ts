/**
 * Format a decimal-string amount (always "1234.56") as PH peso. The input
 * comes from PostgreSQL via the model's `decimal:2` cast so it's always a
 * fixed-precision string — never a float.
 */
export function formatPeso(value: string): string {
    return `₱${Number.parseFloat(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
