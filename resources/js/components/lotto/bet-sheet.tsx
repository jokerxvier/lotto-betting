import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import type { PropsWithChildren } from 'react';
import LottoBall from '@/components/lotto/lotto-ball';
import NumberPad from '@/components/lotto/number-pad';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCart } from '@/contexts/cart-context';
import { randomInt } from '@/lib/random';
import { cn } from '@/lib/utils';

type BetType = {
    id: number;
    code: string;
    label: string;
    base_bet_amount: string;
    base_payout_amount: string;
    payout_strategy: string;
    min_bet: string;
    max_bet: string;
};

export type BetSheetGame = {
    code: string;
    name: string;
    picks_count: number;
    number_min: number;
    number_max: number;
    next_draw_id: number | null;
    next_draw_at: string | null;
    bet_types: BetType[];
};

type Props = PropsWithChildren<{ game: BetSheetGame }>;

type Errors = Record<string, string | undefined>;

const PRESET_AMOUNTS = [1, 5, 10, 25, 50, 100, 250, 500, 750, 1000];

const formatDrawTime = (iso: string | null): string => {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleTimeString('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
    });
};

export default function BetSheet({ game, children }: Props) {
    const [open, setOpen] = useState(false);

    if (!game.next_draw_id || !game.next_draw_at) {
        return <>{children}</>;
    }

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>{children}</SheetTrigger>
            <SheetContent
                side="bottom"
                className="mx-auto max-h-[92svh] max-w-[380px] gap-0 overflow-y-auto rounded-t-2xl p-0"
            >
                <BetWizard
                    game={game}
                    key={open ? 'open' : 'closed'}
                    onDone={() => setOpen(false)}
                />
            </SheetContent>
        </Sheet>
    );
}

function BetWizard({
    game,
    onDone,
}: {
    game: BetSheetGame;
    onDone: () => void;
}) {
    const cart = useCart();
    const [picks, setPicks] = useState<(number | null)[]>(
        Array.from({ length: game.picks_count }, () => null),
    );
    const [betTypeId, setBetTypeId] = useState<number | null>(null);
    const [errors, setErrors] = useState<Errors>({});

    const allPicksMade = picks.every((p) => p !== null);
    const step: 'pick' | 'type' | 'amount' = !allPicksMade
        ? 'pick'
        : betTypeId === null
          ? 'type'
          : 'amount';

    const nextEmptyIdx = picks.findIndex((p) => p === null);
    const selectedType = game.bet_types.find((t) => t.id === betTypeId) ?? null;

    const handlePick = (n: number) => {
        setPicks((prev) => {
            const next = [...prev];
            const idx = next.findIndex((p) => p === null);

            if (idx === -1) {
                return prev;
            }

            next[idx] = n;

            return next;
        });
    };

    const handleLuckyPick = () => {
        setPicks((prev) =>
            prev.map((p) =>
                p === null ? randomInt(game.number_min, game.number_max) : p,
            ),
        );
    };

    const goBack = () => {
        if (step === 'amount') {
            setBetTypeId(null);
            setErrors({});

            return;
        }

        if (step === 'type') {
            setPicks((prev) => {
                const next = [...prev];

                for (let i = next.length - 1; i >= 0; i--) {
                    if (next[i] !== null) {
                        next[i] = null;

                        return next;
                    }
                }

                return next;
            });

            return;
        }

        // step === 'pick'
        if (nextEmptyIdx > 0) {
            setPicks((prev) => {
                const next = [...prev];
                next[nextEmptyIdx - 1] = null;

                return next;
            });

            return;
        }

        onDone();
    };

    const submitWith = (chosenAmount: string) => {
        if (!selectedType || !allPicksMade) {
            return;
        }

        const min = Number.parseFloat(selectedType.min_bet);
        const max = Number.parseFloat(selectedType.max_bet);
        const amountNum = Number.parseFloat(chosenAmount);

        if (
            !Number.isFinite(amountNum) ||
            amountNum < min ||
            amountNum > max
        ) {
            setErrors({
                'legs.0.amount': `Enter an amount between ₱${selectedType.min_bet} and ₱${selectedType.max_bet}.`,
            });

            return;
        }

        if (!game.next_draw_id || !game.next_draw_at) {
            return;
        }

        cart.add({
            drawId: game.next_draw_id,
            drawAt: game.next_draw_at,
            gameCode: game.code,
            gameName: game.name,
            picksCount: game.picks_count,
            gameBetTypeId: selectedType.id,
            betTypeCode: selectedType.code,
            betTypeLabel: selectedType.label,
            numbers: picks as number[],
            amount: amountNum.toFixed(2),
        });
        onDone();
    };

    const padTo = game.picks_count === 2 ? 2 : 1;
    const drawTime = formatDrawTime(game.next_draw_at);

    return (
        <div className="flex flex-col gap-4 px-5 pt-4 pb-8">
            <div className="relative">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    onClick={goBack}
                    aria-label="Back"
                    className="absolute top-0 left-0"
                >
                    <ArrowLeft className="size-5" />
                </Button>
                <SheetTitle className="text-center text-base font-semibold tracking-wide">
                    NEW {drawTime.toUpperCase()} BET
                </SheetTitle>
                <SheetDescription className="sr-only">
                    Place a bet on the upcoming {game.name} draw.
                </SheetDescription>
                <div className="mt-3 flex items-center justify-center gap-2">
                    {picks.map((pick, i) => (
                        <LottoBall
                            key={i}
                            value={pick}
                            padTo={padTo}
                            variant={pick !== null ? 'result' : 'empty'}
                        />
                    ))}
                </div>
            </div>

            <div className="border-t border-border" />

            {step === 'pick' && (
                <div className="space-y-4">
                    <p className="text-center text-sm font-semibold tracking-wide">
                        CHOOSE NUMBER FOR DIGIT #{nextEmptyIdx + 1}
                    </p>
                    <NumberPad
                        min={game.number_min}
                        max={game.number_max}
                        onPick={handlePick}
                        columns={5}
                    />
                    <Button
                        type="button"
                        size="xl"
                        onClick={handleLuckyPick}
                        className="w-full tracking-wide"
                    >
                        LUCKY PICK
                    </Button>
                </div>
            )}

            {step === 'type' && (
                <div className="space-y-4">
                    <p className="text-center text-sm font-semibold tracking-wide">
                        SELECT GAME TYPE
                    </p>
                    <div className="grid grid-cols-2 gap-3">
                        {game.bet_types.map((t) => (
                            <Button
                                key={t.id}
                                type="button"
                                size="xl"
                                onClick={() => setBetTypeId(t.id)}
                                className="tracking-wide uppercase"
                            >
                                {t.label}
                            </Button>
                        ))}
                    </div>
                </div>
            )}

            {step === 'amount' && selectedType && (
                <AmountStep
                    betType={selectedType}
                    errors={errors}
                    onSubmit={submitWith}
                />
            )}
        </div>
    );
}

function AmountStep({
    betType,
    errors,
    onSubmit,
}: {
    betType: BetType;
    errors: Errors;
    onSubmit: (amount: string) => void;
}) {
    const [mode, setMode] = useState<'presets' | 'custom'>('presets');
    const [custom, setCustom] = useState<string>(betType.base_bet_amount);

    const min = Number.parseFloat(betType.min_bet);
    const max = Number.parseFloat(betType.max_bet);

    const tileClass = cn(
        'h-12 rounded-md text-base font-semibold tabular-nums',
    );

    const amountError = errors['legs.0.amount'] ?? errors.amount;

    if (mode === 'custom') {
        return (
            <div className="space-y-4">
                <p className="text-center text-sm font-semibold tracking-wide">
                    CUSTOM AMOUNT
                </p>
                <div className="space-y-1">
                    <Label htmlFor="bet-amount" className="sr-only">
                        Amount in pesos
                    </Label>
                    <Input
                        id="bet-amount"
                        inputMode="decimal"
                        value={custom}
                        onChange={(e) => setCustom(e.target.value)}
                        className="h-12 text-center text-lg tabular-nums"
                        placeholder={betType.base_bet_amount}
                        autoFocus
                    />
                    <p className="text-center text-xs text-muted-foreground">
                        Min ₱{betType.min_bet}, max ₱{betType.max_bet}.
                    </p>
                    {amountError && (
                        <p className="text-center text-xs text-destructive">
                            {amountError}
                        </p>
                    )}
                </div>
                <Button
                    type="button"
                    onClick={() => onSubmit(custom)}
                    className={tileClass + ' w-full uppercase'}
                >
                    Add to cart
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => setMode('presets')}
                    className="mx-auto block text-xs text-muted-foreground"
                >
                    Back to presets
                </Button>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <p className="text-center text-sm font-semibold tracking-wide">
                SELECT BET AMOUNT
            </p>
            <div className="grid grid-cols-5 gap-2">
                {PRESET_AMOUNTS.map((p) => {
                    const inRange = p >= min && p <= max;

                    return (
                        <Button
                            key={p}
                            type="button"
                            disabled={!inRange}
                            onClick={() => onSubmit(p.toFixed(2))}
                            className={tileClass}
                        >
                            {p}
                        </Button>
                    );
                })}
            </div>
            <div className="flex justify-center">
                <Button
                    type="button"
                    onClick={() => setMode('custom')}
                    className={tileClass + ' px-10 uppercase'}
                >
                    Custom
                </Button>
            </div>
            {amountError && (
                <p className="text-center text-xs text-destructive">
                    {amountError}
                </p>
            )}
        </div>
    );
}
