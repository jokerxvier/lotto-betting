import { Form, Head, Link, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock, History, RefreshCw } from 'lucide-react';
import Heading from '@/components/heading';
import GameEmblem from '@/components/lotto/game-emblem';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type AwaitingDraw = {
    id: number;
    draw_at: string;
    cutoff_at: string;
    pending_bets_count: number;
    game: {
        code: string;
        name: string;
        picks_count: number;
    };
};

type Props = {
    draws: AwaitingDraw[];
};

const formatDateTime = (iso: string): string =>
    new Date(iso).toLocaleString('en-PH', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });

export default function AdminDrawsIndex({ draws }: Props) {
    const { props } = usePage<{ flash?: { status?: string } }>();
    const status = props.flash?.status;

    return (
        <>
            <Head title="Admin · Awaiting draws" />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <Heading
                        title="Awaiting draws"
                        description="Draws past their draw time with no result published yet. Publishing settles every pending bet on that draw."
                    />
                    <div className="flex flex-col gap-2 md:flex-row md:items-center">
                        <Form action="/admin/draws/backfill" method="post">
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={processing}
                                    className="w-full md:w-auto"
                                >
                                    {processing ? (
                                        <Spinner className="mr-2" />
                                    ) : (
                                        <History className="mr-2 size-4" />
                                    )}
                                    Backfill last 7 days
                                </Button>
                            )}
                        </Form>
                        <Form action="/admin/draws/scrape" method="post">
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    disabled={processing}
                                    className="w-full md:w-auto"
                                >
                                    {processing ? (
                                        <Spinner className="mr-2" />
                                    ) : (
                                        <RefreshCw className="mr-2 size-4" />
                                    )}
                                    Scrape PCSO results
                                </Button>
                            )}
                        </Form>
                    </div>
                </div>

                {status && (
                    <div className="flex items-center gap-2 rounded-lg border border-success/40 bg-success/10 px-3 py-2 text-sm text-success">
                        <CheckCircle2 className="size-4" />
                        <span>{status}</span>
                    </div>
                )}

                {draws.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-10 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Clock className="size-5" />
                            </div>
                            <p className="text-sm font-semibold">
                                No draws awaiting results
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Once a scheduled draw's time passes and it
                                still has no result, it will appear here.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <ul className="space-y-3">
                        {draws.map((draw) => (
                            <li key={draw.id}>
                                <Card>
                                    <CardHeader className="flex flex-row items-center gap-3 space-y-0">
                                        <GameEmblem
                                            code={draw.game.code}
                                            size="sm"
                                        />
                                        <div className="min-w-0 flex-1">
                                            <CardTitle className="text-base">
                                                {draw.game.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {formatDateTime(draw.draw_at)}
                                            </CardDescription>
                                        </div>
                                        <Button asChild>
                                            <Link
                                                href={`/admin/draws/${draw.id}/result`}
                                            >
                                                Publish
                                            </Link>
                                        </Button>
                                    </CardHeader>
                                    <CardContent className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Pending bets
                                        </span>
                                        <span className="font-bold tabular-nums">
                                            {draw.pending_bets_count}
                                        </span>
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
