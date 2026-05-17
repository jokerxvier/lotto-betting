import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import GameEmblem from '@/components/lotto/game-emblem';
import LottoBall from '@/components/lotto/lotto-ball';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

type Leg = {
    id: number;
    bet_type_code: string;
    bet_type_label: string;
    numbers: number[];
    amount: string;
    potential_payout: string;
    payout: string | null;
};

type Ticket = {
    id: number;
    status: string;
    amount: string;
    potential_payout: string;
    placed_at: string | null;
    settled_at: string | null;
    game: { code: string; name: string; picks_count: number };
    draw: {
        id: number;
        draw_at: string;
        cutoff_at: string;
        status: string;
        result_numbers: number[] | null;
    };
    legs: Leg[];
};

const STATUS_LABEL: Record<string, string> = {
    pending: 'Pending',
    won: 'Won',
    lost: 'Lost',
    void: 'Void',
};

const statusChip = (status: string): string => {
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

const statusAccent = (status: string): string => {
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

const formatDateTime = (iso: string | null): string =>
    iso
        ? new Date(iso).toLocaleString('en-PH', {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          })
        : '—';

export default function TicketShow({ ticket }: { ticket: Ticket }) {
    const padTo = ticket.game.picks_count === 2 ? 2 : 1;

    return (
        <>
            <Head title={`Ticket #${ticket.id}`} />
            <div className="space-y-4 p-4">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="-ml-2 text-muted-foreground"
                >
                    <Link href="/tickets">
                        <ArrowLeft className="mr-1 size-4" />
                        All tickets
                    </Link>
                </Button>

                <Card
                    className={cn(
                        'border-l-4 overflow-hidden',
                        statusAccent(ticket.status),
                    )}
                >
                    <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 pb-3">
                        <div className="flex items-center gap-3">
                            <GameEmblem code={ticket.game.code} size="md" />
                            <div>
                                <p className="text-base leading-tight font-bold">
                                    {ticket.game.name}
                                </p>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Draw {formatDateTime(ticket.draw.draw_at)}
                                </p>
                            </div>
                        </div>
                        <span
                            className={cn(
                                'rounded-full px-2.5 py-0.5 text-[0.65rem] font-bold tracking-wider uppercase',
                                statusChip(ticket.status),
                            )}
                        >
                            {STATUS_LABEL[ticket.status] ?? ticket.status}
                        </span>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm">
                        <dl className="grid grid-cols-2 gap-2">
                            <div className="rounded-lg bg-muted/40 px-3 py-2">
                                <dt className="text-[0.6rem] font-bold tracking-wider text-muted-foreground uppercase">
                                    Ticket
                                </dt>
                                <dd className="font-mono text-sm font-semibold">
                                    #{ticket.id}
                                </dd>
                            </div>
                            <div className="rounded-lg bg-muted/40 px-3 py-2">
                                <dt className="text-[0.6rem] font-bold tracking-wider text-muted-foreground uppercase">
                                    Placed
                                </dt>
                                <dd className="text-xs font-medium">
                                    {formatDateTime(ticket.placed_at)}
                                </dd>
                            </div>
                        </dl>
                        {ticket.settled_at && (
                            <div className="rounded-lg bg-muted/40 px-3 py-2">
                                <dt className="text-[0.6rem] font-bold tracking-wider text-muted-foreground uppercase">
                                    Settled
                                </dt>
                                <dd className="text-xs font-medium">
                                    {formatDateTime(ticket.settled_at)}
                                </dd>
                            </div>
                        )}
                        <div className="flex items-baseline justify-between border-t border-border pt-3 text-sm tabular-nums">
                            <span className="text-muted-foreground">Bet</span>
                            <span className="font-bold">
                                {formatPeso(ticket.amount)}
                            </span>
                        </div>
                        <div
                            className={cn(
                                'flex items-baseline justify-between text-base tabular-nums',
                                ticket.status === 'won' && 'text-success',
                            )}
                        >
                            <span className="text-xs font-bold tracking-wider uppercase">
                                {ticket.status === 'won'
                                    ? 'Payout'
                                    : 'Win up to'}
                            </span>
                            <span className="text-lg font-black">
                                {formatPeso(ticket.potential_payout)}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {ticket.draw.result_numbers && (
                    <Card>
                        <CardHeader className="pb-2">
                            <p className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Drawn result
                            </p>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap items-center justify-center gap-3 rounded-xl bg-muted/40 py-4">
                                {ticket.draw.result_numbers.map((n, i) => (
                                    <LottoBall
                                        key={i}
                                        value={n}
                                        size="lg"
                                        padTo={padTo}
                                    />
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="pb-2">
                        <p className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                            {ticket.legs.length === 1
                                ? 'Bet'
                                : `Bets (${ticket.legs.length})`}
                        </p>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {ticket.legs.map((leg) => (
                            <div
                                key={leg.id}
                                className="space-y-2 rounded-xl border border-border bg-muted/30 p-3"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-[0.65rem] font-bold tracking-wider uppercase">
                                        {leg.bet_type_label}
                                    </span>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {formatPeso(leg.amount)} →{' '}
                                        <span className="font-bold text-foreground">
                                            {formatPeso(leg.potential_payout)}
                                        </span>
                                    </span>
                                </div>
                                <div className="flex flex-wrap items-center gap-1.5">
                                    {leg.numbers.map((n, i) => (
                                        <LottoBall
                                            key={i}
                                            value={n}
                                            size="sm"
                                            variant="pick"
                                            padTo={padTo}
                                        />
                                    ))}
                                </div>
                                {leg.payout !== null && (
                                    <p
                                        className={cn(
                                            'text-xs font-bold tracking-wider uppercase tabular-nums',
                                            Number.parseFloat(leg.payout) > 0
                                                ? 'text-success'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        Payout {formatPeso(leg.payout)}
                                    </p>
                                )}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
