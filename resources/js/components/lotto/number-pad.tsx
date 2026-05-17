import { Button } from '@/components/ui/button';

type Props = {
    min: number;
    max: number;
    onPick: (n: number) => void;
    columns?: number;
    disabled?: boolean;
};

/**
 * Number-tile grid for the bet sheet. Tiles use the default Button variant,
 * which reads its color from `--primary` (Lotto blue per THEME.md §2) so
 * dark mode flips them for free. The fixed column count defaults to 5 to
 * match the reference layout.
 */
export default function NumberPad({
    min,
    max,
    onPick,
    columns = 5,
    disabled = false,
}: Props) {
    const numbers = Array.from({ length: max - min + 1 }, (_, i) => min + i);

    return (
        <div
            className="grid gap-2"
            style={{
                gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
            }}
        >
            {numbers.map((n) => (
                <Button
                    key={n}
                    type="button"
                    size="xl"
                    onClick={() => onPick(n)}
                    disabled={disabled}
                    className="px-0 tabular-nums"
                >
                    {n}
                </Button>
            ))}
        </div>
    );
}
