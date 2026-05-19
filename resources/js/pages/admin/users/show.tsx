import { Head, Link, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ArrowDownCircle,
    ArrowLeft,
    ArrowUpCircle,
    CheckCircle2,
} from 'lucide-react';
import { useMemo } from 'react';
import type { ReactNode } from 'react';
import { WalletAdjustmentDialog } from '@/components/admin/wallet-adjustment-dialog';
import { DataTable } from '@/components/data-table';
import type { PaginatorMeta } from '@/components/data-table';
import Heading from '@/components/heading';
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
    transactions: { data: TransactionRow[] } & PaginatorMeta;
    can_adjust: boolean;
};

type SharedAuth = {
    auth: { user: { id: number } | null };
    flash?: { status?: string };
};

const formatDateTime = (iso: string | null): string => {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
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

export default function AdminUserShow({
    user,
    wallet,
    transactions,
    can_adjust,
}: Props) {
    const { props } = usePage<SharedAuth>();
    const status = props.flash?.status;
    const actorId = props.auth.user?.id ?? 0;

    const columns = useMemo<ColumnDef<TransactionRow>[]>(
        () => [
            {
                accessorKey: 'created_at',
                header: 'When',
                cell: ({ row }) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDateTime(row.original.created_at)}
                    </span>
                ),
            },
            {
                accessorKey: 'type',
                header: 'Type',
                cell: ({ row }) => (
                    <Badge variant="secondary" className="font-normal">
                        {TYPE_LABELS[row.original.type] ?? row.original.type}
                    </Badge>
                ),
            },
            {
                accessorKey: 'amount',
                header: () => <div className="text-right">Amount</div>,
                cell: ({ row }) => {
                    const amount = row.original.amount;
                    const negative = amount.startsWith('-');

                    return (
                        <div
                            className={
                                'text-right font-medium tabular-nums ' +
                                (negative ? 'text-destructive' : 'text-success')
                            }
                        >
                            {negative ? '−' : '+'}
                            {formatPeso(amount.replace(/^-/, ''))}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'balance_after',
                header: () => <div className="text-right">Balance after</div>,
                cell: ({ row }) => (
                    <div className="text-right text-muted-foreground tabular-nums">
                        {formatPeso(row.original.balance_after)}
                    </div>
                ),
            },
            {
                accessorKey: 'actor',
                header: 'Actor',
                cell: ({ row }) => {
                    const actor = row.original.actor;

                    if (actor === null) {
                        return (
                            <span className="text-xs text-muted-foreground">
                                system
                            </span>
                        );
                    }

                    return (
                        <span className="text-sm">
                            {actor.name ?? actor.username ?? `#${actor.id}`}
                        </span>
                    );
                },
            },
            {
                accessorKey: 'note',
                header: 'Note',
                cell: ({ row }) =>
                    row.original.note === null ? (
                        <span className="text-xs text-muted-foreground">—</span>
                    ) : (
                        <span className="text-sm">{row.original.note}</span>
                    ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title={`Admin · ${user.username ?? user.wallet_code}`} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex items-center gap-2">
                    <Button asChild variant="ghost" size="sm">
                        <Link href={adminUsersIndex().url}>
                            <ArrowLeft className="size-4" />
                            All users
                        </Link>
                    </Button>
                </div>

                <Heading
                    title={user.username ?? user.wallet_code}
                    description={user.name ?? 'No display name set.'}
                />

                {status && (
                    <div className="flex items-center gap-2 rounded-lg border border-success/40 bg-success/10 px-3 py-2 text-sm text-success">
                        <CheckCircle2 className="size-4" />
                        <span>{status}</span>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Profile</CardTitle>
                            <CardDescription>
                                Identity and account state.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            <Row label="ID" value={`#${user.id}`} />
                            <Row
                                label="Username"
                                value={user.username ?? '—'}
                            />
                            <Row label="Name" value={user.name ?? '—'} />
                            <Row
                                label="Wallet code"
                                value={
                                    <code className="font-mono">
                                        {user.wallet_code}
                                    </code>
                                }
                            />
                            <Row
                                label="Telegram ID"
                                value={
                                    user.telegram_id === null
                                        ? '—'
                                        : String(user.telegram_id)
                                }
                            />
                            <Row
                                label="Role"
                                value={
                                    user.is_admin ? (
                                        <Badge>Admin</Badge>
                                    ) : (
                                        <Badge variant="secondary">
                                            Player
                                        </Badge>
                                    )
                                }
                            />
                            <Row label="Status" value={user.status} />
                            <Row
                                label="Locked until"
                                value={formatDateTime(user.locked_until)}
                            />
                            <Row
                                label="Joined"
                                value={formatDateTime(user.created_at)}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-start justify-between space-y-0">
                            <div>
                                <CardTitle>Wallet</CardTitle>
                                <CardDescription>
                                    Server-authoritative balance.
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {wallet === null ? (
                                <p className="text-sm text-muted-foreground">
                                    This user has no wallet.
                                </p>
                            ) : (
                                <>
                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="rounded-lg border bg-muted/40 p-3">
                                            <p className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                                                Balance
                                            </p>
                                            <p className="text-2xl font-black tabular-nums">
                                                {formatPeso(wallet.balance)}
                                            </p>
                                        </div>
                                        <div className="rounded-lg border bg-muted/40 p-3">
                                            <p className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                                                Held
                                            </p>
                                            <p className="text-2xl font-black text-muted-foreground tabular-nums">
                                                {formatPeso(
                                                    wallet.held_balance,
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    {can_adjust ? (
                                        <div className="flex gap-2">
                                            <WalletAdjustmentDialog
                                                mode="credit"
                                                user={user}
                                                actorId={actorId}
                                                trigger={
                                                    <Button className="flex-1">
                                                        <ArrowUpCircle className="size-4" />
                                                        Credit
                                                    </Button>
                                                }
                                            />
                                            <WalletAdjustmentDialog
                                                mode="debit"
                                                user={user}
                                                actorId={actorId}
                                                trigger={
                                                    <Button
                                                        variant="destructive"
                                                        className="flex-1"
                                                    >
                                                        <ArrowDownCircle className="size-4" />
                                                        Debit
                                                    </Button>
                                                }
                                            />
                                        </div>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            Admins cannot adjust their own
                                            wallet.
                                        </p>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-2">
                    <h2 className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                        Recent transactions
                    </h2>
                    <DataTable<TransactionRow>
                        columns={columns}
                        data={transactions.data}
                        meta={transactions}
                        onlyOnPaginate={['transactions']}
                        emptyMessage="No transactions yet."
                    />
                </div>
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-right">{value}</span>
        </div>
    );
}
