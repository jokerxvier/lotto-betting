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
 * per rules/THEME.md §2 — no hardcoded colors.
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
                'inline-flex items-center justify-center rounded-full font-bold tabular-nums',
                size === 'sm' && 'size-6 text-xs',
                size === 'md' && 'size-10 text-base',
                size === 'lg' && 'size-14 text-lg',
                variant === 'result' &&
                    'bg-lotto-ball text-lotto-ball-foreground shadow-sm',
                variant === 'pick' &&
                    'bg-primary text-primary-foreground shadow-sm',
                variant === 'empty' &&
                    'border-2 border-dashed border-muted-foreground/40 text-muted-foreground/40',
                className,
            )}
        >
            {display}
        </span>
    );
}
