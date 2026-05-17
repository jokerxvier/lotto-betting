import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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

const statusBadgeClass = (status: string): string => {
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

                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0">
                        <div className="flex items-center gap-3">
                            <div className="flex size-12 shrink-0 items-center justify-center rounded-md bg-primary text-base font-bold text-primary-foreground uppercase">
                                {ticket.game.code}
                            </div>
                            <div>
                                <CardTitle className="text-base">
                                    {ticket.game.name}
                                </CardTitle>
                                <CardDescription>
                                    Draw {formatDateTime(ticket.draw.draw_at)}
                                </CardDescription>
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
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Ticket number
                            </span>
                            <span className="font-mono">#{ticket.id}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Placed
                            </span>
                            <span>{formatDateTime(ticket.placed_at)}</span>
                        </div>
                        {ticket.settled_at && (
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Settled
                                </span>
                                <span>{formatDateTime(ticket.settled_at)}</span>
                            </div>
                        )}
                        <div className="flex justify-between font-semibold tabular-nums">
                            <span>Bet</span>
                            <span>{formatPeso(ticket.amount)}</span>
                        </div>
                        <div
                            className={cn(
                                'flex justify-between font-semibold tabular-nums',
                                ticket.status === 'won' && 'text-success',
                            )}
                        >
                            <span>
                                {ticket.status === 'won'
                                    ? 'Payout'
                                    : 'Potential payout'}
                            </span>
                            <span>{formatPeso(ticket.potential_payout)}</span>
                        </div>
                    </CardContent>
                </Card>

                {ticket.draw.result_numbers && (
                    <Card>
                        <CardHeader>
                            <CardDescription className="tracking-wide uppercase">
                                Drawn result
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap items-center gap-2">
                                {ticket.draw.result_numbers.map((n, i) => (
                                    <LottoBall
                                        key={i}
                                        value={n}
                                        padTo={padTo}
                                    />
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm tracking-wide text-muted-foreground uppercase">
                            {ticket.legs.length === 1 ? 'Leg' : 'Legs'}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {ticket.legs.map((leg) => (
                            <div
                                key={leg.id}
                                className="space-y-2 rounded-md border border-border p-3"
                            >
                                <div className="flex items-center justify-between text-sm">
                                    <span className="font-semibold uppercase">
                                        {leg.bet_type_label}
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {formatPeso(leg.amount)} →{' '}
                                        {formatPeso(leg.potential_payout)}
                                    </span>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
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
                                            'text-xs font-semibold uppercase tabular-nums',
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
