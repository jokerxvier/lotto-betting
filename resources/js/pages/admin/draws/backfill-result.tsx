import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldAlert } from 'lucide-react';
import Heading from '@/components/heading';
import GameEmblem from '@/components/lotto/game-emblem';
import LottoBall from '@/components/lotto/lotto-ball';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDrawRow, slotLabel } from '@/lib/draw-time';

type Counts = {
    created: number;
    updated: number;
    unchanged: number;
    skipped: number;
    skipped_settled: number;
    skipped_no_match: number;
    skipped_invalid: number;
};

type Row = {
    draw_id: number;
    game: string;
    draw_at: string;
    status:
        | 'created'
        | 'updated'
        | 'unchanged'
        | 'skipped_settled'
        | 'skipped_no_match'
        | 'skipped_invalid';
    numbers: number[] | null;
    prev_numbers: number[] | null;
};

type Props = {
    from: string;
    to: string;
    generated: number;
    counts: Counts;
    rows: Row[];
    source_label: string;
};

const STATUS_LABEL: Record<Row['status'], string> = {
    created: 'Created',
    updated: 'Updated',
    unchanged: 'Unchanged',
    skipped_settled: 'Skipped — settled',
    skipped_no_match: 'No match',
    skipped_invalid: 'Invalid numbers',
};

function StatusBadge({ status }: { status: Row['status'] }) {
    const cls =
        status === 'created'
            ? 'bg-success/15 text-success'
            : status === 'updated'
              ? 'bg-primary/15 text-primary'
              : status === 'unchanged'
                ? 'bg-muted text-muted-foreground'
                : status === 'skipped_settled'
                  ? 'bg-warning/15 text-warning'
                  : 'bg-destructive/10 text-destructive';

    return (
        <span
            className={
                'rounded-full px-2 py-0.5 text-[0.6rem] font-bold tracking-wider uppercase ' +
                cls
            }
        >
            {STATUS_LABEL[status]}
        </span>
    );
}

function StatChip({
    label,
    value,
    tone = 'neutral',
}: {
    label: string;
    value: number;
    tone?: 'neutral' | 'success' | 'primary' | 'warning' | 'destructive';
}) {
    const toneClass =
        tone === 'success'
            ? 'bg-success/10 text-success border-success/40'
            : tone === 'primary'
              ? 'bg-primary/10 text-primary border-primary/40'
              : tone === 'warning'
                ? 'bg-warning/10 text-warning border-warning/40'
                : tone === 'destructive'
                  ? 'bg-destructive/10 text-destructive border-destructive/40'
                  : 'bg-muted text-muted-foreground border-border';

    return (
        <div
            className={
                'flex flex-col items-start gap-0.5 rounded-lg border px-3 py-2 ' +
                toneClass
            }
        >
            <p className="text-[0.6rem] font-bold tracking-wider uppercase">
                {label}
            </p>
            <p className="text-xl leading-tight font-black tabular-nums">
                {value}
            </p>
        </div>
    );
}

export default function BackfillResult({
    from,
    to,
    generated,
    counts,
    rows,
    source_label,
}: Props) {
    const total = rows.length;
    const seededClause =
        generated > 0
            ? ` Seeded ${generated} missing draw slot${generated === 1 ? '' : 's'} for the range first.`
            : '';

    return (
        <>
            <Head title="Admin · Backfill results" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
                        title="Backfill results"
                        description={`Parsed ${total} draw${total === 1 ? '' : 's'} from ${source_label} between ${from} and ${to}. No bets were settled.${seededClause}`}
                    />
                    <Button
                        asChild
                        variant="outline"
                        className="w-full md:w-auto"
                    >
                        <Link href="/admin/draws">
                            <ArrowLeft className="mr-2 size-4" />
                            Back to draws
                        </Link>
                    </Button>
                </div>

                <section className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                    <StatChip
                        label="Seeded"
                        value={generated}
                        tone={generated > 0 ? 'primary' : 'neutral'}
                    />
                    <StatChip
                        label="Created"
                        value={counts.created}
                        tone="success"
                    />
                    <StatChip
                        label="Updated"
                        value={counts.updated}
                        tone="primary"
                    />
                    <StatChip label="Unchanged" value={counts.unchanged} />
                    <StatChip
                        label="Skipped"
                        value={counts.skipped}
                        tone={counts.skipped > 0 ? 'warning' : 'neutral'}
                    />
                </section>

                {counts.skipped_settled > 0 && (
                    <div className="flex items-start gap-2 rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-sm text-warning">
                        <ShieldAlert className="mt-0.5 size-4 shrink-0" />
                        <div>
                            <p className="font-semibold">
                                {counts.skipped_settled} settled draw
                                {counts.skipped_settled === 1 ? '' : 's'} were
                                not overwritten.
                            </p>
                            <p className="text-xs text-warning/80">
                                Backfill refuses to change numbers on draws that
                                already paid out — manual ledger reversal
                                required first.
                            </p>
                        </div>
                    </div>
                )}

                {total === 0 ? (
                    <Card>
                        <CardContent className="py-10 text-center text-sm text-muted-foreground">
                            No draws found in the selected range.
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="space-y-2">
                        {rows.map((row) => (
                            <li key={row.draw_id}>
                                <Card>
                                    <CardHeader className="flex flex-row items-center gap-3 space-y-0 pb-3">
                                        <GameEmblem code={row.game} size="sm" />
                                        <div className="min-w-0 flex-1">
                                            <CardTitle className="text-sm">
                                                {row.game.toUpperCase()} ·{' '}
                                                {slotLabel(row.draw_at)}
                                            </CardTitle>
                                            <CardDescription className="text-xs">
                                                {formatDrawRow(row.draw_at)} ·
                                                draw #{row.draw_id}
                                            </CardDescription>
                                        </div>
                                        <StatusBadge status={row.status} />
                                    </CardHeader>
                                    <CardContent className="space-y-2 pb-4">
                                        {row.numbers ? (
                                            <div className="flex flex-wrap items-center gap-1.5">
                                                {row.numbers.map((n, i) => (
                                                    <LottoBall
                                                        key={`${row.draw_id}-n-${i}`}
                                                        value={n}
                                                        size="sm"
                                                        variant="result"
                                                        padTo={
                                                            row.game === '2d'
                                                                ? 2
                                                                : 1
                                                        }
                                                    />
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-xs text-muted-foreground italic">
                                                Scraper returned no numbers for
                                                this slot.
                                            </p>
                                        )}
                                        {row.prev_numbers &&
                                            row.status === 'updated' && (
                                                <p className="text-xs text-muted-foreground">
                                                    Was:{' '}
                                                    <span className="font-mono">
                                                        {row.prev_numbers.join(
                                                            '-',
                                                        )}
                                                    </span>
                                                </p>
                                            )}
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
