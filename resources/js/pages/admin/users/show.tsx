import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowLeft,
    ArrowUpRight,
    CheckCircle2,
    LockKeyhole,
    Sparkles,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { WalletAdjustmentDialog } from '@/components/admin/wallet-adjustment-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatPeso } from '@/lib/money';
import {
    absoluteDateTime,
    dateBucket,
    relativeTime,
} from '@/lib/relative-time';
import { cn } from '@/lib/utils';
import { index as adminUsersIndex } from '@/routes/admin/users';

type UserPayload = {
    id: number;
    name: string | null;
    username: string | null;
    telegram_id: number | null;
    wallet_code: string;
    is_admin: boolean;
    status: string;
    locked_until: string | null;
    created_at: string | null;
};

type WalletPayload = {
    id: number;
    balance: string;
    held_balance: string;
    version: number;
} | null;

type TransactionRow = {
    id: number;
    type: string;
    amount: string;
    balance_after: string;
    note: string | null;
    actor: {
        id: number;
        name: string | null;
        username: string | null;
    } | null;
    created_at: string | null;
};

type Props = {
    user: UserPayload;
    wallet: WalletPayload;
    transactions: { data: TransactionRow[] };
    can_adjust: boolean;
};

type SharedAuth = {
    auth: { user: { id: number } | null };
    flash?: { status?: string };
};

const TYPE_LABELS: Record<string, string> = {
    admin_credit: 'Admin credit',
    admin_debit: 'Admin debit',
    admin_topup: 'Admin top-up',
    deposit: 'Deposit',
    bet_debit: 'Bet placed',
    bet_payout: 'Bet payout',
    refund: 'Refund',
    withdrawal: 'Withdrawal',
};

const initialsFor = (
    actor: { name: string | null; username: string | null } | null,
): string => {
    if (actor === null) {
        return '··';
    }

    const source = actor.name ?? actor.username ?? '';

    return source.slice(0, 2).toUpperCase() || '··';
};

const isPositive = (value: string): boolean => {
    const n = Number.parseFloat(value);

    return !Number.isNaN(n) && n > 0;
};

export default function AdminUserShow({
    user,
    wallet,
    transactions,
    can_adjust,
}: Props) {
    const { props } = usePage<SharedAuth>();
    const status = props.flash?.status;
    const actorId = props.auth.user?.id ?? 0;

    const locked = user.status !== 'active';
    const heldBalance = wallet?.held_balance ?? '0.00';

    const groupedTransactions = transactions.data.reduce<
        Record<string, TransactionRow[]>
    >((acc, tx) => {
        const bucket = dateBucket(tx.created_at);

        if (!acc[bucket]) {
            acc[bucket] = [];
        }

        acc[bucket].push(tx);

        return acc;
    }, {});
    const bucketOrder = ['Today', 'Yesterday', 'This week', 'Earlier'];

    return (
        <>
            <Head title={`Admin · ${user.username ?? user.wallet_code}`} />
            <div className="mx-auto max-w-5xl space-y-8 p-4 md:p-8">
                {/* Breadcrumb-style back link */}
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="h-7 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
                    >
                        <Link href={adminUsersIndex().url}>
                            <ArrowLeft className="size-3.5" />
                            Users
                        </Link>
                    </Button>
                    <span className="text-muted-foreground/40">/</span>
                    <span className="truncate font-mono text-foreground">
                        {user.username ?? user.wallet_code}
                    </span>
                </div>

                {/* Editorial header */}
                <header className="space-y-3">
                    <p className="text-[0.65rem] font-bold tracking-[0.2em] text-muted-foreground uppercase">
                        Player profile · #{user.id}
                    </p>
                    <div className="flex flex-wrap items-end gap-3">
                        <h1 className="text-3xl font-bold tracking-tight">
                            {user.username ?? user.wallet_code}
                        </h1>
                        {user.is_admin && (
                            <Badge variant="default">Admin</Badge>
                        )}
                        {locked && (
                            <Badge
                                variant="outline"
                                className="border-destructive/40 text-destructive"
                            >
                                <LockKeyhole className="size-3" />
                                {user.status}
                            </Badge>
                        )}
                    </div>
                    {user.name && (
                        <p className="text-sm text-muted-foreground">
                            {user.name}
                        </p>
                    )}
                </header>

                {status && (
                    <div className="flex items-center gap-2 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm text-success">
                        <CheckCircle2 className="size-4" />
                        <span>{status}</span>
                    </div>
                )}

                {/* Hero wallet panel — money is the protagonist. */}
                {wallet !== null ? (
                    <section className="relative overflow-hidden rounded-2xl border bg-card">
                        <div
                            aria-hidden
                            className="absolute inset-x-0 top-0 h-0.5 bg-primary"
                        />
                        <div className="grid gap-6 p-6 md:grid-cols-[1fr_auto] md:gap-8 md:p-8">
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <p className="text-[0.65rem] font-bold tracking-[0.18em] text-muted-foreground uppercase">
                                        Wallet balance
                                    </p>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <span className="cursor-default font-mono text-[0.65rem] text-muted-foreground/70 tabular-nums">
                                                v{wallet.version}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            Optimistic lock counter. Bumps on
                                            every mutation.
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                                <p
                                    className={cn(
                                        'font-mono text-5xl font-bold tracking-tight tabular-nums sm:text-6xl',
                                        isPositive(wallet.balance)
                                            ? 'text-foreground'
                                            : 'text-muted-foreground/50',
                                    )}
                                >
                                    {formatPeso(wallet.balance)}
                                </p>
                                {isPositive(heldBalance) && (
                                    <p className="font-mono text-sm text-muted-foreground tabular-nums">
                                        {formatPeso(heldBalance)} held in flight
                                    </p>
                                )}
                            </div>

                            {can_adjust ? (
                                <div className="flex flex-row gap-2 md:flex-col md:items-stretch md:justify-end">
                                    <WalletAdjustmentDialog
                                        mode="credit"
                                        user={user}
                                        actorId={actorId}
                                        currentBalance={wallet.balance}
                                        trigger={
                                            <Button
                                                className="gap-2"
                                                variant="default"
                                            >
                                                <ArrowUpRight className="size-4" />
                                                Credit
                                            </Button>
                                        }
                                    />
                                    <WalletAdjustmentDialog
                                        mode="debit"
                                        user={user}
                                        actorId={actorId}
                                        currentBalance={wallet.balance}
                                        trigger={
                                            <Button
                                                className="gap-2"
                                                variant="outline"
                                            >
                                                <ArrowDownRight className="size-4" />
                                                Debit
                                            </Button>
                                        }
                                    />
                                </div>
                            ) : (
                                <p className="self-end text-xs text-muted-foreground">
                                    Admins cannot adjust their own wallet.
                                </p>
                            )}
                        </div>
                    </section>
                ) : (
                    <Card>
                        <CardContent className="p-6 text-sm text-muted-foreground">
                            This user has no wallet record.
                        </CardContent>
                    </Card>
                )}

                {/* Profile metadata strip */}
                <section>
                    <h2 className="mb-3 text-[0.65rem] font-bold tracking-[0.18em] text-muted-foreground uppercase">
                        Identity
                    </h2>
                    <div className="grid grid-cols-2 gap-x-6 gap-y-4 rounded-lg border bg-card p-5 sm:grid-cols-4">
                        <Meta label="Username" value={user.username ?? '—'} />
                        <Meta label="Name" value={user.name ?? '—'} />
                        <Meta
                            label="Wallet code"
                            value={
                                <code className="font-mono text-sm">
                                    {user.wallet_code}
                                </code>
                            }
                        />
                        <Meta
                            label="Telegram"
                            value={
                                user.telegram_id === null ? (
                                    '—'
                                ) : (
                                    <span className="font-mono tabular-nums">
                                        {user.telegram_id}
                                    </span>
                                )
                            }
                        />
                        <Meta
                            label="Joined"
                            value={
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <span className="cursor-default">
                                            {relativeTime(user.created_at)}
                                        </span>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {absoluteDateTime(user.created_at)}
                                    </TooltipContent>
                                </Tooltip>
                            }
                        />
                        <Meta
                            label="Status"
                            value={
                                <span
                                    className={cn(
                                        'inline-flex items-center gap-1.5',
                                        locked
                                            ? 'text-destructive'
                                            : 'text-success',
                                    )}
                                >
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'size-1.5 rounded-full',
                                            locked
                                                ? 'bg-destructive'
                                                : 'bg-success',
                                        )}
                                    />
                                    {user.status}
                                </span>
                            }
                        />
                        {user.locked_until && (
                            <Meta
                                label="Locked until"
                                value={absoluteDateTime(user.locked_until)}
                            />
                        )}
                    </div>
                </section>

                {/* Ledger */}
                <section>
                    <div className="mb-4 flex items-baseline justify-between">
                        <h2 className="text-[0.65rem] font-bold tracking-[0.18em] text-muted-foreground uppercase">
                            Transaction ledger
                        </h2>
                        <p className="text-xs text-muted-foreground tabular-nums">
                            {transactions.data.length} most recent
                        </p>
                    </div>

                    {transactions.data.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center gap-2 p-10 text-center">
                                <Sparkles className="size-5 text-muted-foreground/40" />
                                <p className="text-sm text-muted-foreground">
                                    No movement yet. Credit or debit the wallet
                                    to start a ledger.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="overflow-hidden rounded-lg border bg-card">
                            {bucketOrder
                                .filter((bucket) => groupedTransactions[bucket])
                                .map((bucket, bucketIdx) => (
                                    <div key={bucket}>
                                        {bucketIdx > 0 && (
                                            <Separator className="bg-border/60" />
                                        )}
                                        <div className="flex items-center justify-between border-b bg-muted/30 px-5 py-2">
                                            <span className="text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                                                {bucket}
                                            </span>
                                            <span className="font-mono text-[0.65rem] text-muted-foreground tabular-nums">
                                                {
                                                    groupedTransactions[bucket]
                                                        .length
                                                }
                                            </span>
                                        </div>
                                        <ul className="divide-y">
                                            {groupedTransactions[bucket].map(
                                                (tx) => (
                                                    <LedgerRow
                                                        key={tx.id}
                                                        tx={tx}
                                                    />
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

function LedgerRow({ tx }: { tx: TransactionRow }) {
    const negative = tx.amount.startsWith('-');
    const isCredit = !negative;
    const Icon = isCredit ? ArrowUpRight : ArrowDownRight;

    return (
        <li className="grid grid-cols-[auto_1fr_auto] items-start gap-3 px-5 py-3.5 transition-colors hover:bg-muted/20 sm:grid-cols-[auto_1.4fr_1fr_auto] sm:items-center">
            <span
                className={cn(
                    'mt-0.5 inline-flex size-7 items-center justify-center rounded-full sm:mt-0',
                    isCredit
                        ? 'bg-success/10 text-success'
                        : 'bg-destructive/10 text-destructive',
                )}
                aria-hidden
            >
                <Icon className="size-3.5" />
            </span>

            <div className="min-w-0">
                <p className="truncate text-sm leading-tight font-medium">
                    {TYPE_LABELS[tx.type] ?? tx.type}
                </p>
                {tx.note ? (
                    <p className="truncate text-xs leading-tight text-muted-foreground">
                        {tx.note}
                    </p>
                ) : (
                    <p className="text-xs leading-tight text-muted-foreground/60">
                        —
                    </p>
                )}
            </div>

            <div className="hidden items-center gap-2 sm:flex">
                <span className="flex size-6 items-center justify-center rounded-full border bg-muted/40 font-mono text-[0.6rem] font-semibold text-muted-foreground tabular-nums">
                    {initialsFor(tx.actor)}
                </span>
                <div className="min-w-0">
                    <p className="truncate text-xs leading-tight text-foreground/80">
                        {tx.actor === null
                            ? 'system'
                            : (tx.actor.name ??
                              tx.actor.username ??
                              `#${tx.actor.id}`)}
                    </p>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="cursor-default text-[0.65rem] text-muted-foreground tabular-nums">
                                {relativeTime(tx.created_at)}
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {absoluteDateTime(tx.created_at)}
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>

            <div className="text-right">
                <p
                    className={cn(
                        'font-mono text-sm font-semibold tracking-tight tabular-nums',
                        isCredit ? 'text-success' : 'text-destructive',
                    )}
                >
                    {isCredit ? '+' : '−'}
                    {formatPeso(tx.amount.replace(/^-/, ''))}
                </p>
                <p className="font-mono text-[0.65rem] text-muted-foreground tabular-nums">
                    {formatPeso(tx.balance_after)}
                </p>
            </div>
        </li>
    );
}

function Meta({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="space-y-0.5">
            <p className="text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground uppercase">
                {label}
            </p>
            <div className="text-sm">{value}</div>
        </div>
    );
}
