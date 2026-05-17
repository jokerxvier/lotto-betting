import { Head } from '@inertiajs/react';
import { Trophy } from 'lucide-react';
import { useMemo } from 'react';
import GameEmblem from '@/components/lotto/game-emblem';
import LottoBall from '@/components/lotto/lotto-ball';
import { Card, CardContent } from '@/components/ui/card';
import { useCountdown } from '@/hooks/use-countdown';
import { cn } from '@/lib/utils';

type ResultState = 'open' | 'awaiting' | 'settled';

type DrawResultRow = {
    id: number;
    draw_at: string;
    cutoff_at: string;
    state: ResultState;
    numbers: number[] | null;
    game: {
        code: string;
        name: string;
        picks_count: number;
    };
};

type Props = {
    results: DrawResultRow[];
};

const formatDayHeading = (iso: string): string =>
    new Date(iso).toLocaleDateString('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });

const formatDrawTime = (iso: string): string =>
    new Date(iso).toLocaleTimeString('en-PH', {
        hour: 'numeric',
        minute: '2-digit',
    });

function CountdownPill({ targetIso }: { targetIso: string }) {
    const c = useCountdown(targetIso);

    if (c.expired) {
        return null;
    }

    const pad = (n: number) => String(n).padStart(2, '0');
    const label =
        c.hours > 0
            ? `${c.hours}h ${pad(c.minutes)}m`
            : `${pad(c.minutes)}:${pad(c.seconds)}`;

    return (
        <span className="flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-0.5 text-[0.7rem] font-bold tabular-nums text-primary">
            <span className="size-1.5 rounded-full bg-primary" />
            Closes in {label}
        </span>
    );
}

function StateBadge({ state }: { state: ResultState }) {
    switch (state) {
        case 'settled':
            return (
                <span className="rounded-full bg-success/15 px-2 py-0.5 text-[0.65rem] font-bold tracking-wider text-success uppercase">
                    Result
                </span>
            );
        case 'open':
            return (
                <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[0.65rem] font-bold tracking-wider text-primary uppercase">
                    Open
                </span>
            );
        case 'awaiting':
        default:
            return (
                <span className="rounded-full bg-warning/15 px-2 py-0.5 text-[0.65rem] font-bold tracking-wider text-warning uppercase">
                    Awaiting
                </span>
            );
    }
}

function ResultRow({ row }: { row: DrawResultRow }) {
    const padTo = row.game.picks_count === 2 ? 2 : 1;

    return (
        <Card>
            <CardContent className="space-y-3 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-2.5">
                        <GameEmblem code={row.game.code} size="sm" />
                        <div className="min-w-0">
                            <p className="text-sm leading-tight font-bold">
                                {row.game.name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formatDrawTime(row.draw_at)} draw
                            </p>
                        </div>
                    </div>
                    <StateBadge state={row.state} />
                </header>

                {row.state === 'settled' && row.numbers && (
                    <div className="flex flex-wrap items-center gap-2 rounded-lg bg-muted/40 px-3 py-2.5">
                        {row.numbers.map((n, i) => (
                            <LottoBall
                                key={i}
                                value={n}
                                size="md"
                                variant="result"
                                padTo={padTo}
                            />
                        ))}
                    </div>
                )}

                {row.state === 'awaiting' && (
                    <div className="flex flex-wrap items-center gap-2">
                        {Array.from({ length: row.game.picks_count }).map(
                            (_, i) => (
                                <LottoBall key={i} size="md" variant="empty" />
                            ),
                        )}
                        <span className="text-xs text-muted-foreground">
                            Awaiting result
                        </span>
                    </div>
                )}

                {row.state === 'open' && (
                    <div className="flex items-center justify-between gap-2">
                        <span className="text-xs text-muted-foreground">
                            Bets open until{' '}
                            <span className="font-semibold text-foreground">
                                {formatDrawTime(row.cutoff_at)}
                            </span>
                        </span>
                        <CountdownPill targetIso={row.cutoff_at} />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function ResultsIndex({ results }: Props) {
    const grouped = useMemo(() => {
        const buckets = new Map<string, DrawResultRow[]>();

        for (const r of results) {
            const dayKey = r.draw_at.slice(0, 10);

            if (!buckets.has(dayKey)) {
                buckets.set(dayKey, []);
            }

            buckets.get(dayKey)!.push(r);
        }

        return Array.from(buckets.entries()).map(([dayKey, list]) => ({
            key: dayKey,
            label: formatDayHeading(list[0].draw_at),
            rows: list,
        }));
    }, [results]);

    return (
        <>
            <Head title="Results" />
            <div className="space-y-5 p-4">
                <header className="space-y-0.5">
                    <h1 className="text-xl font-bold tracking-tight">
                        Results
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Recent draws and winning numbers.
                    </p>
                </header>

                {results.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-card p-8 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <Trophy className="size-5" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">
                                No draws this week
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">
                                No draws have been scheduled in the last 7
                                days.
                            </p>
                        </div>
                    </div>
                ) : (
                    grouped.map((group) => (
                        <section key={group.key} className="space-y-2">
                            <h2
                                className={cn(
                                    'sticky top-[3.5rem] z-10 -mx-4 px-4 py-1',
                                    'bg-background/85 text-[0.7rem] font-bold tracking-wider text-muted-foreground uppercase backdrop-blur-md',
                                )}
                            >
                                {group.label}
                            </h2>
                            <div className="space-y-2">
                                {group.rows.map((row) => (
                                    <ResultRow key={row.id} row={row} />
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </div>
        </>
    );
}
