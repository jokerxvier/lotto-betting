import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import GameEmblem from '@/components/lotto/game-emblem';
import LottoBall from '@/components/lotto/lotto-ball';
import { Card, CardContent } from '@/components/ui/card';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

type BetStatus = 'pending' | 'won' | 'lost' | 'void' | string;

export type Ticket = {
    id: number;
    status: BetStatus;
    amount: string;
    potential_payout: string;
    placed_at: string | null;
    settled_at: string | null;
    game: {
        code: string;
        name: string;
        picks_count: number;
    };
    draw: {
        id: number;
        draw_at: string;
        cutoff_at: string;
        status: string;
        result_numbers: number[] | null;
    };
    preview_leg: {
        bet_type_code: string;
        bet_type_label: string;
        numbers: number[];
    } | null;
};

const STATUS_LABEL: Record<string, string> = {
    pending: 'Pending',
    won: 'Won',
    lost: 'Lost',
    void: 'Void',
};

/**
 * Left-border accent maps the ticket status to a semantic token. This is the
 * single visual cue that lets a tickets list be scanned for wins at a glance.
 */
const statusAccent = (status: BetStatus): string => {
    switch (status) {
        case 'won':
            return 'border-l-success';
        case 'lost':
            return 'border-l-muted-foreground/40';
        case 'void':
            return 'border-l-destructive';
        case 'pending':
        default:
            return 'border-l-primary';
    }
};

const statusChip = (status: BetStatus): string => {
    switch (status) {
        case 'won':
            return 'bg-success/15 text-success';
        case 'lost':
            return 'bg-muted text-muted-foreground';
        case 'void':
            return 'bg-destructive/15 text-destructive';
        case 'pending':
        default:
            return 'bg-primary/10 text-primary';
    }
};

const formatDrawDateTime = (iso: string): string =>
    new Date(iso).toLocaleString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

export default function TicketCard({ ticket }: { ticket: Ticket }) {
    const padTo = ticket.game.picks_count === 2 ? 2 : 1;

    return (
        <Link
            href={`/tickets/${ticket.id}`}
            className="block transition-transform active:scale-[0.99]"
        >
            <Card
                className={cn(
                    'border-l-4 transition-colors hover:bg-muted/40',
                    statusAccent(ticket.status),
                )}
            >
                <CardContent className="space-y-2.5 p-4">
                    <header className="flex items-start justify-between gap-3">
                        <div className="flex items-center gap-2.5">
                            <GameEmblem code={ticket.game.code} size="sm" />
                            <div className="min-w-0">
                                <p className="text-sm leading-tight font-bold">
                                    {ticket.game.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {formatDrawDateTime(ticket.draw.draw_at)}
                                </p>
                            </div>
                        </div>
                        <span
                            className={cn(
                                'rounded-full px-2 py-0.5 text-[0.65rem] font-bold tracking-wider uppercase',
                                statusChip(ticket.status),
                            )}
                        >
                            {STATUS_LABEL[ticket.status] ?? ticket.status}
                        </span>
                    </header>

                    {ticket.preview_leg && (
                        <div className="flex flex-wrap items-center gap-1.5">
                            {ticket.preview_leg.numbers.map((n, i) => (
                                <LottoBall
                                    key={i}
                                    value={n}
                                    size="sm"
                                    variant="pick"
                                    padTo={padTo}
                                />
                            ))}
                            <span className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                                {ticket.preview_leg.bet_type_label}
                            </span>
                        </div>
                    )}

                    <footer className="flex items-baseline justify-between border-t border-border/60 pt-2 text-sm tabular-nums">
                        <span className="text-xs text-muted-foreground">
                            Bet{' '}
                            <span className="font-bold text-foreground">
                                {formatPeso(ticket.amount)}
                            </span>
                        </span>
                        <span
                            className={cn(
                                'flex items-center gap-1 font-bold',
                                ticket.status === 'won' && 'text-success',
                            )}
                        >
                            {ticket.status === 'won' ? 'Won ' : 'Win up to '}
                            {formatPeso(ticket.potential_payout)}
                            <ChevronRight className="size-3.5 text-muted-foreground" />
                        </span>
                    </footer>
                </CardContent>
            </Card>
        </Link>
    );
}
