import { Head, Link } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { useMemo, useState } from 'react';
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
            <div className="space-y-5 p-4">
                <header className="space-y-0.5">
                    <h1 className="text-xl font-bold tracking-tight">
                        My tickets
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Your recent bets.
                    </p>
                </header>

                <div
                    role="tablist"
                    className="inline-flex rounded-full bg-muted p-0.5"
                >
                    {(['schedule', 'status'] as ViewMode[]).map((mode) => (
                        <Button
                            key={mode}
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => setView(mode)}
                            role="tab"
                            aria-selected={view === mode}
                            className={cn(
                                'rounded-full px-4 text-xs font-semibold capitalize transition-all',
                                view === mode
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:bg-transparent hover:text-foreground',
                            )}
                        >
                            {mode}
                        </Button>
                    ))}
                </div>

                {tickets.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-card p-8 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <Receipt className="size-5" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">No bets yet</p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Tap a game on the Lotto tab to place your first
                                bet.
                            </p>
                        </div>
                        <Button asChild size="sm" className="mt-1">
                            <Link href="/lotto">Go to Lotto</Link>
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-5">
                        {grouped.map((group) => (
                            <section key={group.key} className="space-y-2">
                                <h2 className="sticky top-[3.5rem] z-10 -mx-4 bg-background/85 px-4 py-1 text-[0.7rem] font-bold tracking-wider text-muted-foreground uppercase backdrop-blur-md">
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
