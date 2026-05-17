import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import TicketCard from '@/components/lotto/ticket-card';
import type { Ticket } from '@/components/lotto/ticket-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    tickets: Ticket[];
};

type ViewMode = 'schedule' | 'status';

const STATUS_ORDER = ['pending', 'won', 'lost', 'void'];
const STATUS_LABEL: Record<string, string> = {
    pending: 'Pending',
    won: 'Won',
    lost: 'Lost',
    void: 'Void',
};

const formatDrawHeading = (iso: string): string =>
    new Date(iso).toLocaleDateString('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });

export default function TicketsIndex({ tickets }: Props) {
    const [view, setView] = useState<ViewMode>('schedule');

    const grouped = useMemo(() => {
        if (view === 'status') {
            const buckets = new Map<string, Ticket[]>();

            for (const t of tickets) {
                const key = t.status;

                if (!buckets.has(key)) {
                    buckets.set(key, []);
                }

                buckets.get(key)!.push(t);
            }

            return STATUS_ORDER.filter((s) => buckets.has(s)).map((status) => ({
                key: status,
                label: STATUS_LABEL[status] ?? status,
                tickets: buckets.get(status)!,
            }));
        }

        // schedule — group by draw date (YYYY-MM-DD)
        const buckets = new Map<string, Ticket[]>();

        for (const t of tickets) {
            const dayKey = t.draw.draw_at.slice(0, 10);

            if (!buckets.has(dayKey)) {
                buckets.set(dayKey, []);
            }

            buckets.get(dayKey)!.push(t);
        }

        return Array.from(buckets.entries()).map(([dayKey, list]) => ({
            key: dayKey,
            label: formatDrawHeading(list[0].draw.draw_at),
            tickets: list,
        }));
    }, [tickets, view]);

    return (
        <>
            <Head title="Tickets" />
            <div className="space-y-4 p-4">
                <Heading title="My tickets" description="Your recent bets." />

                <div className="flex gap-2" role="tablist">
                    {(['schedule', 'status'] as ViewMode[]).map((mode) => (
                        <Button
                            key={mode}
                            type="button"
                            variant={view === mode ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setView(mode)}
                            role="tab"
                            aria-selected={view === mode}
                            className={cn('capitalize')}
                        >
                            {mode}
                        </Button>
                    ))}
                </div>

                {tickets.length === 0 ? (
                    <p className="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                        No bets yet. Tap{' '}
                        <span className="font-semibold text-foreground">
                            New bet
                        </span>{' '}
                        on the Lotto tab to place one.
                    </p>
                ) : (
                    <div className="space-y-5">
                        {grouped.map((group) => (
                            <section key={group.key} className="space-y-2">
                                <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    {group.label}
                                </h2>
                                <div className="space-y-2">
                                    {group.tickets.map((t) => (
                                        <TicketCard key={t.id} ticket={t} />
                                    ))}
                                </div>
                            </section>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
