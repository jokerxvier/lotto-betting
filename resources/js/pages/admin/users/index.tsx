import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { CheckCircle2, ExternalLink, Search } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { PaginatorMeta } from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatPeso } from '@/lib/money';
import { show as adminUsersShow } from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string | null;
    username: string | null;
    telegram_id: number | null;
    wallet_code: string;
    is_admin: boolean;
    status: string;
    created_at: string | null;
    balance: string;
    held_balance: string;
};

type Props = {
    users: {
        data: UserRow[];
    } & PaginatorMeta;
    filters: {
        search: string;
    };
};

const formatDate = (iso: string | null): string => {
    if (iso === null) {
        return '—';
    }

    return new Date(iso).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

export default function AdminUsersIndex({ users, filters }: Props) {
    const { props } = usePage<{ flash?: { status?: string } }>();
    const status = props.flash?.status;

    const [search, setSearch] = useState(filters.search);
    const initialSearch = useRef(filters.search);

    useEffect(() => {
        if (search === initialSearch.current) {
            return;
        }

        const timeout = window.setTimeout(() => {
            router.get('/admin/users', search === '' ? {} : { search }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['users', 'filters'],
            });
            initialSearch.current = search;
        }, 250);

        return () => window.clearTimeout(timeout);
    }, [search]);

    const columns = useMemo<ColumnDef<UserRow>[]>(
        () => [
            {
                accessorKey: 'username',
                header: 'Username',
                cell: ({ row }) => (
                    <div className="font-medium">
                        {row.original.username ?? (
                            <span className="text-muted-foreground">
                                (no username)
                            </span>
                        )}
                    </div>
                ),
            },
            {
                accessorKey: 'name',
                header: 'Name',
                cell: ({ row }) => row.original.name ?? '—',
            },
            {
                accessorKey: 'wallet_code',
                header: 'Wallet code',
                cell: ({ row }) => (
                    <code className="font-mono text-xs">
                        {row.original.wallet_code}
                    </code>
                ),
            },
            {
                accessorKey: 'telegram_id',
                header: 'Telegram',
                cell: ({ row }) =>
                    row.original.telegram_id !== null ? (
                        <span className="font-mono text-xs">
                            {row.original.telegram_id}
                        </span>
                    ) : (
                        '—'
                    ),
            },
            {
                accessorKey: 'balance',
                header: () => <div className="text-right">Balance</div>,
                cell: ({ row }) => (
                    <div className="text-right font-medium tabular-nums">
                        {formatPeso(row.original.balance)}
                    </div>
                ),
            },
            {
                accessorKey: 'held_balance',
                header: () => <div className="text-right">Held</div>,
                cell: ({ row }) => (
                    <div className="text-right text-muted-foreground tabular-nums">
                        {formatPeso(row.original.held_balance)}
                    </div>
                ),
            },
            {
                accessorKey: 'is_admin',
                header: 'Role',
                cell: ({ row }) =>
                    row.original.is_admin ? (
                        <Badge variant="default">Admin</Badge>
                    ) : (
                        <Badge variant="secondary">Player</Badge>
                    ),
            },
            {
                accessorKey: 'created_at',
                header: 'Joined',
                cell: ({ row }) => (
                    <span className="text-xs text-muted-foreground">
                        {formatDate(row.original.created_at)}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) => (
                    <Button
                        asChild
                        size="sm"
                        variant="outline"
                        className="ml-auto"
                    >
                        <Link
                            href={adminUsersShow(row.original.id).url}
                            prefetch
                        >
                            View
                            <ExternalLink className="size-3.5" />
                        </Link>
                    </Button>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Admin · Users" />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title="Users"
                    description="Search players, view wallets, and credit or debit balances."
                />

                {status && (
                    <div className="flex items-center gap-2 rounded-lg border border-success/40 bg-success/10 px-3 py-2 text-sm text-success">
                        <CheckCircle2 className="size-4" />
                        <span>{status}</span>
                    </div>
                )}

                <Card>
                    <CardContent className="p-4">
                        <div className="relative max-w-sm">
                            <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.currentTarget.value)
                                }
                                placeholder="Search username, name, wallet code, or telegram id"
                                className="pl-9"
                                aria-label="Search users"
                            />
                        </div>
                    </CardContent>
                </Card>

                <DataTable<UserRow>
                    columns={columns}
                    data={users.data}
                    meta={users}
                    onlyOnPaginate={['users']}
                    emptyMessage="No users match that search."
                />
            </div>
        </>
    );
}
