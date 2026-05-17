import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, AlertTriangle } from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatPeso } from '@/lib/money';

type Props = {
    draw: {
        id: number;
        draw_at: string;
        pending_bets_count: number;
        pending_potential_payout: string;
        game: {
            code: string;
            name: string;
            picks_count: number;
            number_min: number;
            number_max: number;
        };
    };
};

const formatDateTime = (iso: string): string =>
    new Date(iso).toLocaleString('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

export default function AdminDrawResult({ draw }: Props) {
    const [picks, setPicks] = useState<string[]>(
        Array.from({ length: draw.game.picks_count }, () => ''),
    );

    const allFilled = picks.every((p) => p !== '');
    const padTo = draw.game.picks_count === 2 ? 2 : 1;

    const numericPicks = useMemo(
        () =>
            picks.map((p) => {
                const n = Number.parseInt(p, 10);

                return Number.isNaN(n) ? null : n;
            }),
        [picks],
    );

    const setPickAt = (idx: number, raw: string) => {
        // Allow empty / digits only
        if (!/^\d*$/.test(raw)) {
            return;
        }

        setPicks((prev) => {
            const next = [...prev];
            next[idx] = raw;

            return next;
        });
    };

    return (
        <>
            <Head title={`Admin · Publish ${draw.game.name}`} />
            <div className="space-y-6 p-4 md:p-6">
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="-ml-2 text-muted-foreground"
                >
                    <Link href="/admin/draws">
                        <ArrowLeft className="mr-1 size-4" />
                        Awaiting draws
                    </Link>
                </Button>

                <Heading
                    title="Publish result"
                    description="Enter the official numbers. Settlement runs immediately and is shown on the next page."
                />

                <Card>
                    <CardHeader className="flex flex-row items-center gap-3 space-y-0">
                        <GameEmblem code={draw.game.code} size="sm" />
                        <div className="min-w-0 flex-1">
                            <CardTitle className="text-base">
                                {draw.game.name}
                            </CardTitle>
                            <CardDescription>
                                {formatDateTime(draw.draw_at)} draw
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-3 text-sm">
                        <div className="rounded-lg bg-muted/40 p-3">
                            <p className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Pending bets
                            </p>
                            <p className="text-base font-bold tabular-nums">
                                {draw.pending_bets_count}
                            </p>
                        </div>
                        <div className="rounded-lg bg-muted/40 p-3">
                            <p className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Max payout
                            </p>
                            <p className="text-base font-bold tabular-nums">
                                {formatPeso(draw.pending_potential_payout)}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <Form
                    action={`/admin/draws/${draw.id}/result`}
                    method="post"
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm tracking-wide uppercase">
                                        Winning numbers
                                    </CardTitle>
                                    <CardDescription>
                                        Range: {draw.game.number_min}–
                                        {draw.game.number_max}. Order matters
                                        for Target; sorted equality for
                                        Rambolito.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-3 gap-3 sm:grid-cols-6">
                                        {picks.map((p, i) => (
                                            <div
                                                key={i}
                                                className="space-y-1"
                                            >
                                                <Label
                                                    htmlFor={`pick-${i}`}
                                                    className="text-[0.6rem] font-bold tracking-wider text-muted-foreground uppercase"
                                                >
                                                    Digit #{i + 1}
                                                </Label>
                                                <Input
                                                    id={`pick-${i}`}
                                                    name={`numbers[${i}]`}
                                                    inputMode="numeric"
                                                    value={p}
                                                    onChange={(e) =>
                                                        setPickAt(
                                                            i,
                                                            e.target.value,
                                                        )
                                                    }
                                                    min={draw.game.number_min}
                                                    max={draw.game.number_max}
                                                    placeholder="0"
                                                    className="h-12 text-center text-xl font-bold tabular-nums"
                                                    autoFocus={i === 0}
                                                />
                                                <InputError
                                                    message={
                                                        errors[
                                                            `numbers.${i}` as keyof typeof errors
                                                        ]
                                                    }
                                                />
                                            </div>
                                        ))}
                                    </div>
                                    <InputError
                                        message={errors.numbers}
                                    />

                                    {allFilled && (
                                        <div className="rounded-lg border border-border bg-muted/30 p-4">
                                            <p className="mb-3 text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                                                Preview
                                            </p>
                                            <div className="flex flex-wrap items-center justify-center gap-3">
                                                {numericPicks.map((n, i) =>
                                                    n === null ? null : (
                                                        <LottoBall
                                                            key={i}
                                                            value={n}
                                                            size="lg"
                                                            padTo={padTo}
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="flex items-start gap-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-xs text-warning-foreground">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning" />
                                <p>
                                    <span className="font-bold uppercase">
                                        Real money.
                                    </span>{' '}
                                    Publishing immediately settles all pending
                                    bets and credits winners. There is no
                                    undo.
                                </p>
                            </div>

                            <Button
                                type="submit"
                                size="xl"
                                disabled={!allFilled || processing}
                                className="w-full uppercase"
                            >
                                {processing && (
                                    <Spinner className="mr-2" />
                                )}
                                Publish & settle
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
