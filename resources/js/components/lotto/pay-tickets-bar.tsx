import { router } from '@inertiajs/react';
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
        <div className="sticky bottom-14 z-10 px-4 pb-2">
            <button
                type="button"
                onClick={pay}
                disabled={processing}
                className={cn(
                    'flex w-full items-center gap-3 rounded-full bg-warning px-4 py-3 text-warning-foreground shadow-lg transition-opacity',
                    processing && 'opacity-60',
                )}
            >
                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-warning-foreground/15 text-sm font-bold tabular-nums">
                    {legs.length}
                </span>
                <span className="flex-1 text-sm font-bold tracking-wide uppercase">
                    {processing ? 'Placing…' : 'Pay tickets'}
                </span>
                <span className="text-base font-bold tabular-nums">
                    {formatPeso(totalAmount)}
                </span>
            </button>
        </div>
    );
}
