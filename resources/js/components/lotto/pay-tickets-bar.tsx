import { router } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useCart } from '@/contexts/cart-context';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

export default function PayTicketsBar() {
    const { legs, clear, totalAmount } = useCart();
    const [processing, setProcessing] = useState(false);

    if (legs.length === 0) {
        return null;
    }

    const pay = () => {
        if (processing) {
            return;
        }

        setProcessing(true);

        router.post(
            '/bets/cart',
            {
                legs: legs.map((l) => ({
                    leg_token: l.id,
                    draw_id: l.drawId,
                    game_bet_type_id: l.gameBetTypeId,
                    numbers: l.numbers,
                    amount: l.amount,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    clear();
                    toast.success(
                        legs.length === 1
                            ? '1 ticket placed'
                            : `${legs.length} tickets placed`,
                    );
                },
                onError: (e) => {
                    const flat = Object.values(
                        (e ?? {}) as Record<string, string | string[]>,
                    ).flat();
                    const message = flat.length
                        ? flat.join(' ')
                        : 'Could not place tickets';

                    toast.error(message);
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="pointer-events-none sticky bottom-14 z-10 px-3 pb-2">
            <button
                type="button"
                onClick={pay}
                disabled={processing}
                aria-label={`Pay ${legs.length} ticket${legs.length === 1 ? '' : 's'} totalling ${formatPeso(totalAmount)}`}
                className={cn(
                    'pointer-events-auto group relative flex w-full items-center gap-3 overflow-hidden rounded-full bg-warning px-4 py-3 text-warning-foreground shadow-[0_4px_18px_-2px_oklch(0.75_0.16_75/0.55)] transition-all',
                    'before:absolute before:inset-0 before:bg-[radial-gradient(circle_at_20%_-20%,oklch(1_0_0/0.35),transparent_55%)] before:opacity-80 before:transition-opacity',
                    'active:scale-[0.98] active:shadow-[0_2px_8px_-2px_oklch(0.75_0.16_75/0.45)]',
                    processing && 'opacity-70',
                )}
            >
                <span className="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full bg-warning-foreground/15 text-sm font-extrabold tabular-nums shadow-inner">
                    {legs.length}
                </span>
                <span className="relative z-10 flex-1 text-left text-sm font-bold tracking-wider uppercase">
                    {processing ? 'Placing tickets…' : 'Pay tickets'}
                </span>
                <span className="relative z-10 text-base font-extrabold tabular-nums">
                    {formatPeso(totalAmount)}
                </span>
                <ArrowRight
                    className="relative z-10 size-4 transition-transform group-hover:translate-x-0.5"
                    aria-hidden
                />
            </button>
        </div>
    );
}
