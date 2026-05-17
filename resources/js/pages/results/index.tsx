import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import LottoBall from '@/components/lotto/lotto-ball';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useCountdown } from '@/hooks/use-countdown';

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
        <Badge variant="outline" className="font-mono tabular-nums">
            Closes in {label}
        </Badge>
    );
}

function StateBadge({ state }: { state: ResultState }) {
    switch (state) {
        case 'settled':
            return (
                <Badge className="bg-success text-success-foreground hover:bg-success/90 uppercase">
                    Result
                </Badge>
            );
        case 'open':
            return (
                <Badge className="bg-primary text-primary-foreground hover:bg-primary/90 uppercase">
                    Open
                </Badge>
            );
        case 'awaiting':
        default:
            return (
                <Badge variant="outline" className="uppercase">
                    Awaiting
                </Badge>
            );
    }
}

function ResultRow({ row }: { row: DrawResultRow }) {
    const padTo = row.game.picks_count === 2 ? 2 : 1;

    return (
        <Card>
            <CardContent className="space-y-3 p-4">
                <header className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary text-xs font-bold text-primary-foreground uppercase">
                            {row.game.code}
                        </div>
                        <div className="min-w-0">
                            <p className="text-sm leading-tight font-semibold">
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
                    <div className="flex flex-wrap items-center gap-2">
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
                            Bets open until {formatDrawTime(row.cutoff_at)}
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
            <div className="space-y-4 p-4">
                <Heading
                    title="Results"
                    description="Recent draws and the winning numbers."
                />

                {results.length === 0 ? (
                    <p className="rounded-md border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                        No draws scheduled in the last 7 days.
                    </p>
                ) : (
                    <div className="space-y-5">
                        {grouped.map((group) => (
                            <section key={group.key} className="space-y-2">
                                <h2 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    {group.label}
                                </h2>
                                <div className="space-y-2">
                                    {group.rows.map((row) => (
                                        <ResultRow key={row.id} row={row} />
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
