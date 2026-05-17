import { Clock, Plus, X } from 'lucide-react';
import BetSheet from '@/components/lotto/bet-sheet';
import type { BetSheetGame } from '@/components/lotto/bet-sheet';
import GameEmblem from '@/components/lotto/game-emblem';
import LottoBall from '@/components/lotto/lotto-ball';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import { useCart } from '@/contexts/cart-context';
import type { DraftLeg } from '@/contexts/cart-context';
import { useCountdown } from '@/hooks/use-countdown';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

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
        <div className="flex items-center gap-2.5 py-2.5">
            <div className="flex flex-wrap items-center gap-1">
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
            <span className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                {leg.betTypeLabel}
            </span>
            <span className="ml-auto rounded-md bg-muted px-2 py-1 text-sm font-bold tabular-nums">
                {formatPeso(leg.amount)}
            </span>
            <button
                type="button"
                onClick={onRemove}
                aria-label="Remove leg"
                className="-mr-1 flex size-7 shrink-0 items-center justify-center rounded-full text-destructive transition-colors hover:bg-destructive/10 active:scale-95"
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
    const padTo = game.picks_count === 2 ? 2 : 1;

    // < 15 min remaining → soft warning. < 2 min → destructive.
    const cutoffWarning =
        !cutoffPassed &&
        countdown.hours === 0 &&
        countdown.minutes < 15;
    const cutoffCritical =
        !cutoffPassed &&
        countdown.hours === 0 &&
        countdown.minutes < 2;

    return (
        <Card className="overflow-hidden">
            <CardHeader className="flex flex-row items-start gap-3 space-y-0 pb-3">
                <GameEmblem code={game.code} size="md" />
                <div className="min-w-0 flex-1">
                    <h3 className="text-base leading-tight font-bold">
                        {game.name}
                    </h3>
                    {game.payout_label && (
                        <p className="mt-0.5 text-xs font-semibold tabular-nums text-muted-foreground">
                            {game.payout_label}
                        </p>
                    )}
                </div>
                {game.latest_result_numbers &&
                    game.latest_result_numbers.length > 0 && (
                        <div className="flex flex-col items-end gap-1">
                            <span className="text-[0.6rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Latest
                            </span>
                            <div className="flex flex-wrap items-center justify-end gap-1">
                                {game.latest_result_numbers.map((n, i) => (
                                    <LottoBall
                                        key={i}
                                        value={n}
                                        size="sm"
                                        padTo={padTo}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
            </CardHeader>

            <CardContent className="space-y-3 pt-0">
                {game.next_draw_at ? (
                    <section className="rounded-xl border border-border bg-muted/40">
                        <div className="flex items-center gap-2 px-3 py-2.5 text-sm">
                            <Clock
                                className={cn(
                                    'size-4',
                                    cutoffCritical
                                        ? 'text-destructive'
                                        : cutoffWarning
                                          ? 'text-warning'
                                          : 'text-primary',
                                )}
                            />
                            <span className="font-bold">
                                {formatTime(game.next_draw_at)}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                draw
                            </span>
                            {!cutoffPassed && (
                                <span
                                    className={cn(
                                        'ml-auto flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[0.7rem] font-bold tabular-nums',
                                        cutoffCritical
                                            ? 'bg-destructive/10 text-destructive'
                                            : cutoffWarning
                                              ? 'bg-warning/15 text-warning'
                                              : 'bg-primary/10 text-primary',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'size-1.5 rounded-full',
                                            cutoffCritical
                                                ? 'animate-pulse bg-destructive'
                                                : cutoffWarning
                                                  ? 'animate-pulse bg-warning'
                                                  : 'bg-primary',
                                        )}
                                    />
                                    {countdown.hours > 0 &&
                                        `${countdown.hours}h `}
                                    {String(countdown.minutes).padStart(2, '0')}
                                    m{' '}
                                    {String(countdown.seconds).padStart(2, '0')}
                                    s
                                </span>
                            )}
                            {cutoffPassed && (
                                <Badge
                                    variant="outline"
                                    className="ml-auto text-[0.65rem] font-bold tracking-wider uppercase"
                                >
                                    Closed
                                </Badge>
                            )}
                        </div>

                        {drafts.length > 0 && (
                            <div className="divide-y divide-border/60 border-t border-border/60 px-3">
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
                    <p className="rounded-xl border border-dashed border-border bg-muted/30 px-3 py-2.5 text-sm text-muted-foreground">
                        No upcoming draw scheduled.
                    </p>
                )}

                <section className="flex gap-2">
                    <BetSheet game={game}>
                        <Button
                            className="flex-1 font-bold tracking-wide uppercase"
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
                        className="font-bold tracking-wide uppercase"
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
