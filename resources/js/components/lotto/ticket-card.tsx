import { Link } from '@inertiajs/react';
import LottoBall from '@/components/lotto/lotto-ball';
import { Badge } from '@/components/ui/badge';
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

const statusBadgeClass = (status: BetStatus): string => {
    switch (status) {
        case 'won':
            return 'bg-success text-success-foreground hover:bg-success/90';
        case 'lost':
            return 'bg-muted text-muted-foreground';
        case 'void':
            return 'bg-destructive text-destructive-foreground hover:bg-destructive/90';
        case 'pending':
        default:
            return 'bg-primary text-primary-foreground hover:bg-primary/90';
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
            className="block transition-opacity hover:opacity-90"
        >
            <Card>
                <CardContent className="space-y-3 p-4">
                    <header className="flex items-start justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-xs font-bold text-primary-foreground uppercase">
                                {ticket.game.code}
                            </div>
                            <div className="min-w-0">
                                <p className="text-sm leading-tight font-semibold">
                                    {ticket.game.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {formatDrawDateTime(ticket.draw.draw_at)}
                                </p>
                            </div>
                        </div>
                        <Badge
                            className={cn(
                                'uppercase',
                                statusBadgeClass(ticket.status),
                            )}
                        >
                            {STATUS_LABEL[ticket.status] ?? ticket.status}
                        </Badge>
                    </header>

                    {ticket.preview_leg && (
                        <div className="flex flex-wrap items-center gap-2">
                            {ticket.preview_leg.numbers.map((n, i) => (
                                <LottoBall
                                    key={i}
                                    value={n}
                                    size="sm"
                                    variant="pick"
                                    padTo={padTo}
                                />
                            ))}
                            <span className="text-xs text-muted-foreground uppercase">
                                {ticket.preview_leg.bet_type_label}
                            </span>
                        </div>
                    )}

                    <footer className="flex items-baseline justify-between text-sm tabular-nums">
                        <span className="text-muted-foreground">
                            Bet {formatPeso(ticket.amount)}
                        </span>
                        <span
                            className={cn(
                                'font-semibold',
                                ticket.status === 'won' && 'text-success',
                            )}
                        >
                            {ticket.status === 'won' ? 'Won ' : 'Win up to '}
                            {formatPeso(ticket.potential_payout)}
                        </span>
                    </footer>
                </CardContent>
            </Card>
        </Link>
    );
}
