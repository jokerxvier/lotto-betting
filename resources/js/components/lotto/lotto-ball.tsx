import { cn } from '@/lib/utils';

type Size = 'sm' | 'md' | 'lg';
type Variant = 'result' | 'pick' | 'empty';

type Props = {
    value?: number | string | null;
    size?: Size;
    variant?: Variant;
    /** Pad numeric values with leading zeros to this width (e.g. 2 → `07`). */
    padTo?: number;
    className?: string;
};

/**
 * Lotto PH's signature number chip. Three variants:
 *  - `result` — filled brand yellow, used for drawn results.
 *  - `pick`   — filled brand primary, used for in-progress picks.
 *  - `empty`  — dashed outline placeholder for un-picked slots.
 *
 * Tokens (`lotto-ball`, `primary`, `border`) come from `resources/css/app.css`
 * per rules/THEME.md §2 — no hardcoded colors. The radial highlight is a
 * tiny tactile cue so the ball reads as a 3D chip instead of a flat circle.
 */
export default function LottoBall({
    value,
    size = 'md',
    variant = 'result',
    padTo,
    className,
}: Props) {
    const display =
        value === null || value === undefined
            ? ''
            : padTo && typeof value === 'number'
              ? String(value).padStart(padTo, '0')
              : String(value);

    return (
        <span
            className={cn(
                'relative inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-bold tabular-nums select-none',
                'before:pointer-events-none before:absolute before:inset-0 before:rounded-full',
                size === 'sm' && 'size-7 text-[0.7rem]',
                size === 'md' && 'size-10 text-base',
                size === 'lg' && 'size-14 text-xl',
                variant === 'result' &&
                    'bg-lotto-ball text-lotto-ball-foreground shadow-[0_1px_2px_rgba(0,0,0,0.18),0_4px_10px_-2px_oklch(0.88_0.18_95/0.45)] before:bg-[radial-gradient(circle_at_30%_25%,oklch(1_0_0/0.45),transparent_55%)]',
                variant === 'pick' &&
                    'bg-primary text-primary-foreground shadow-[0_1px_2px_rgba(0,0,0,0.2),0_4px_10px_-2px_oklch(0.58_0.2_255/0.4)] before:bg-[radial-gradient(circle_at_30%_25%,oklch(1_0_0/0.32),transparent_55%)]',
                variant === 'empty' &&
                    'border-2 border-dashed border-muted-foreground/40 bg-transparent text-muted-foreground/40',
                className,
            )}
        >
            <span className="relative z-10">{display}</span>
        </span>
    );
}
