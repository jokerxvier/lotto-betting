import { Clock, Plus, X } from 'lucide-react';
import BetSheet from '@/components/lotto/bet-sheet';
import type { BetSheetGame } from '@/components/lotto/bet-sheet';
import LottoBall from '@/components/lotto/lotto-ball';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCart } from '@/contexts/cart-context';
import type { DraftLeg } from '@/contexts/cart-context';
import { useCountdown } from '@/hooks/use-countdown';
import { formatPeso } from '@/lib/money';

export type GameCardData = BetSheetGame & {
    id: number;
    payout_label: string | null;
    target_bet_type_id: number | null;
    latest_result_numbers: number[] | null;
    latest_drawn_at: string | null;
    next_cutoff_at: string | null;
};

const formatTime = (iso: string) =>
    new Date(iso).toLocaleTimeString('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
    });

function DraftLegRow({
    leg,
    onRemove,
}: {
    leg: DraftLeg;
    onRemove: () => void;
}) {
    const padTo = leg.picksCount === 2 ? 2 : 1;

    return (
        <div className="flex items-center gap-3 py-2">
            <div className="flex flex-wrap items-center gap-1.5">
                {leg.numbers.map((n, i) => (
                    <LottoBall
                        key={i}
                        value={n}
                        size="sm"
                        variant="result"
                        padTo={padTo}
                    />
                ))}
            </div>
            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {leg.betTypeLabel}
            </span>
            <span className="ml-auto rounded border border-border px-2 py-0.5 text-sm font-semibold tabular-nums">
                {formatPeso(leg.amount)}
            </span>
            <button
                type="button"
                onClick={onRemove}
                aria-label="Remove leg"
                className="text-destructive transition-opacity hover:opacity-70"
            >
                <X className="size-4" />
            </button>
        </div>
    );
}

export default function GameCard({ game }: { game: GameCardData }) {
    const cart = useCart();
    const countdown = useCountdown(game.next_cutoff_at);
    const cutoffPassed = countdown.expired;
    const drafts = game.next_draw_id ? cart.legsForDraw(game.next_draw_id) : [];

    return (
        <Card className="overflow-hidden">
            <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                <div className="flex size-12 shrink-0 items-center justify-center rounded-md bg-primary text-base font-bold text-primary-foreground uppercase">
                    {game.code}
                </div>
                <div className="flex-1">
                    <CardTitle className="text-base">{game.name}</CardTitle>
                    {game.payout_label && (
                        <CardDescription className="font-medium">
                            {game.payout_label}
                        </CardDescription>
                    )}
                </div>
                {game.latest_result_numbers &&
                    game.latest_result_numbers.length > 0 && (
                        <div className="flex flex-col items-end gap-1">
                            <span className="text-[0.65rem] font-semibold tracking-wide text-muted-foreground uppercase">
                                Latest result
                            </span>
                            <div className="flex flex-wrap items-center gap-1">
                                {game.latest_result_numbers.map((n, i) => (
                                    <LottoBall
                                        key={i}
                                        value={n}
                                        size="sm"
                                        padTo={game.picks_count === 2 ? 2 : 1}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
            </CardHeader>

            <CardContent className="space-y-3">
                {game.next_draw_at ? (
                    <section className="rounded-md bg-muted/40">
                        <div className="flex items-center gap-2 px-3 py-2 text-sm">
                            <Clock className="size-4 text-muted-foreground" />
                            <span className="font-medium">
                                {formatTime(game.next_draw_at)} draw
                            </span>
                            {!cutoffPassed && (
                                <Badge
                                    variant="secondary"
                                    className="ml-auto tabular-nums"
                                >
                                    Cutoff in{' '}
                                    {countdown.hours > 0 &&
                                        `${countdown.hours}h `}
                                    {String(countdown.minutes).padStart(2, '0')}
                                    m{' '}
                                    {String(countdown.seconds).padStart(2, '0')}
                                    s
                                </Badge>
                            )}
                            {cutoffPassed && (
                                <Badge variant="outline" className="ml-auto">
                                    Closed
                                </Badge>
                            )}
                        </div>

                        {drafts.length > 0 && (
                            <div className="divide-y divide-border border-t border-border px-3">
                                {drafts.map((leg) => (
                                    <DraftLegRow
                                        key={leg.id}
                                        leg={leg}
                                        onRemove={() => cart.remove(leg.id)}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No upcoming draw scheduled.
                    </p>
                )}

                <section className="flex gap-2">
                    <BetSheet game={game}>
                        <Button
                            className="flex-1"
                            disabled={!game.next_draw_id || cutoffPassed}
                            title={
                                game.next_draw_id
                                    ? undefined
                                    : 'No upcoming draw'
                            }
                        >
                            New bet
                        </Button>
                    </BetSheet>
                    <Button
                        variant="outline"
                        disabled
                        title="Advance betting ships in Phase 2"
                    >
                        <Plus className="mr-1 size-4" />
                        Advance
                    </Button>
                </section>
            </CardContent>
        </Card>
    );
}
