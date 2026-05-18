import { Head, Link, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Receipt,
    Settings as SettingsIcon,
    Sparkles,
    Trophy,
    Wallet as WalletIcon,
} from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatPeso } from '@/lib/money';

type Props = {
    stats: {
        awaiting_count: number;
        bets_today_count: number;
        settled_today_payout: string;
    };
    settings: {
        suggestions_enabled: boolean;
        auto_publish_enabled: boolean;
    };
};

type SharedAuth = {
    auth: {
        user: { name: string | null; username: string | null } | null;
    };
    flash?: { status?: string };
};

function StatCard({
    label,
    value,
    icon: Icon,
    tone = 'neutral',
}: {
    label: string;
    value: string;
    icon: ComponentType<SVGProps<SVGSVGElement>>;
    tone?: 'neutral' | 'warning' | 'success';
}) {
    const toneClass =
        tone === 'warning'
            ? 'bg-warning/15 text-warning'
            : tone === 'success'
              ? 'bg-success/15 text-success'
              : 'bg-primary/10 text-primary';

    return (
        <Card>
            <CardContent className="flex items-center gap-3 p-4">
                <div
                    className={
                        'flex size-10 shrink-0 items-center justify-center rounded-lg ' +
                        toneClass
                    }
                >
                    <Icon className="size-5" />
                </div>
                <div className="min-w-0">
                    <p className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p className="text-lg leading-tight font-black tabular-nums">
                        {value}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

function QuickLinkCard({
    href,
    icon: Icon,
    title,
    description,
}: {
    href: string;
    icon: ComponentType<SVGProps<SVGSVGElement>>;
    title: string;
    description: string;
}) {
    return (
        <Link
            href={href}
            className="block transition-transform active:scale-[0.99]"
        >
            <Card className="transition-colors hover:bg-muted/40">
                <CardHeader className="flex flex-row items-start gap-3 space-y-0">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-5" />
                    </div>
                    <div className="flex-1">
                        <CardTitle className="text-base">{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </div>
                </CardHeader>
            </Card>
        </Link>
    );
}

export default function AdminDashboard({ stats, settings }: Props) {
    const { props } = usePage<SharedAuth>();
    const status = props.flash?.status;
    const adminName =
        props.auth.user?.name ?? props.auth.user?.username ?? 'Admin';

    return (
        <>
            <Head title="Admin" />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title={`Hi, ${adminName}`}
                    description="Operator dashboard. Pick a tool below or check the day at a glance."
                />

                {status && (
                    <div className="flex items-center gap-2 rounded-lg border border-success/40 bg-success/10 px-3 py-2 text-sm text-success">
                        <CheckCircle2 className="size-4" />
                        <span>{status}</span>
                    </div>
                )}

                <section className="space-y-2">
                    <h2 className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                        Today
                    </h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <StatCard
                            label="Awaiting draws"
                            value={String(stats.awaiting_count)}
                            icon={Clock}
                            tone={
                                stats.awaiting_count > 0
                                    ? 'warning'
                                    : 'neutral'
                            }
                        />
                        <StatCard
                            label="Bets today"
                            value={String(stats.bets_today_count)}
                            icon={Receipt}
                        />
                        <StatCard
                            label="Paid out today"
                            value={formatPeso(stats.settled_today_payout)}
                            icon={Trophy}
                            tone="success"
                        />
                    </div>
                </section>

                <section className="space-y-2">
                    <h2 className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                        Tools
                    </h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <QuickLinkCard
                            href="/admin/draws"
                            icon={Trophy}
                            title="Awaiting draws"
                            description="Publish results and settle pending bets."
                        />
                        <QuickLinkCard
                            href="/admin/settings"
                            icon={SettingsIcon}
                            title="Settings"
                            description="Scraper + auto-publish toggles."
                        />
                        <QuickLinkCard
                            href="/admin/wallets"
                            icon={WalletIcon}
                            title="Top up wallet"
                            description="Credit a player by wallet code."
                        />
                    </div>
                </section>

                <section className="space-y-2">
                    <h2 className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                        Automation
                    </h2>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <Card>
                            <CardContent className="flex items-center gap-3 p-4">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                    <Sparkles className="size-5" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-bold">
                                        PCSO suggestions
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Pre-fills the publish form
                                    </p>
                                </div>
                                <span
                                    className={
                                        settings.suggestions_enabled
                                            ? 'rounded-full bg-success/15 px-2 py-0.5 text-[0.6rem] font-bold tracking-wider text-success uppercase'
                                            : 'rounded-full bg-muted px-2 py-0.5 text-[0.6rem] font-bold tracking-wider text-muted-foreground uppercase'
                                    }
                                >
                                    {settings.suggestions_enabled
                                        ? 'On'
                                        : 'Off'}
                                </span>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="flex items-center gap-3 p-4">
                                <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-destructive/10 text-destructive">
                                    <AlertTriangle className="size-5" />
                                </div>
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-bold">
                                        Auto-publish
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Settles without admin review
                                    </p>
                                </div>
                                <span
                                    className={
                                        settings.auto_publish_enabled
                                            ? 'rounded-full bg-destructive/15 px-2 py-0.5 text-[0.6rem] font-bold tracking-wider text-destructive uppercase'
                                            : 'rounded-full bg-muted px-2 py-0.5 text-[0.6rem] font-bold tracking-wider text-muted-foreground uppercase'
                                    }
                                >
                                    {settings.auto_publish_enabled
                                        ? 'On'
                                        : 'Off'}
                                </span>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <div className="text-right">
                    <Button asChild variant="outline" size="sm">
                        <Link href="/admin/settings">
                            Manage automation
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
