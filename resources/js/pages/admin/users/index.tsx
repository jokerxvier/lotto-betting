import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    ArrowUpRight,
    CheckCircle2,
    LockKeyhole,
    Search,
    Send,
    UserPlus,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { PaginatorMeta } from '@/components/paginator-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatPeso } from '@/lib/money';
import { absoluteDateTime, relativeTime } from '@/lib/relative-time';
import { cn } from '@/lib/utils';
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

const initialsFor = (row: UserRow): string => {
    const source = row.username ?? row.name ?? row.wallet_code;

    return source.slice(0, 2).toUpperCase();
};

const isPositive = (value: string): boolean => {
    const n = Number.parseFloat(value);

    return !Number.isNaN(n) && n > 0;
};

/**
 * Roll up cheap stats from the current page's users so the header strip
 * shows something concrete without a separate API call. These are
 * page-scoped (not totals) — the "page X of Y" badge keeps that honest.
 */
function pageStats(data: UserRow[]): {
    admins: number;
    funded: number;
    telegram: number;
    locked: number;
} {
    let admins = 0;
    let funded = 0;
    let telegram = 0;
    let locked = 0;

    for (const row of data) {
        if (row.is_admin) {
            admins++;
        }

        if (isPositive(row.balance)) {
            funded++;
        }

        if (row.telegram_id !== null) {
            telegram++;
        }

        if (row.status !== 'active') {
            locked++;
        }
    }

    return { admins, funded, telegram, locked };
}

export default function AdminUsersIndex({ users, filters }: Props) {
    const { props } = usePage<{ flash?: { status?: string } }>();
    const status = props.flash?.status;

    const [search, setSearch] = useState(filters.search);
    const initialSearch = useRef(filters.search);
    const searchInputRef = useRef<HTMLInputElement>(null);

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

    useEffect(() => {
        const onKeydown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
                event.preventDefault();
                searchInputRef.current?.focus();
                searchInputRef.current?.select();
            }
        };
        window.addEventListener('keydown', onKeydown);

        return () => window.removeEventListener('keydown', onKeydown);
    }, []);

    const stats = useMemo(() => pageStats(users.data), [users.data]);

    const columns = useMemo<ColumnDef<UserRow>[]>(
        () => [
            {
                id: 'identity',
                header: () => (
                    <span className="text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground/80 uppercase">
                        Player
                    </span>
                ),
                cell: ({ row }) => {
                    const locked = row.original.status !== 'active';

                    return (
                        <div className="flex items-center gap-3">
                            <div className="relative shrink-0">
                                <span className="flex size-9 items-center justify-center rounded-md border bg-muted/50 font-mono text-[0.7rem] font-semibold text-foreground/80 tabular-nums">
                                    {initialsFor(row.original)}
                                </span>
                                <span
                                    aria-hidden
                                    className={cn(
                                        'absolute -right-0.5 -bottom-0.5 size-2 rounded-full ring-2 ring-background',
                                        locked
                                            ? 'bg-destructive'
                                            : 'bg-success',
                                    )}
                                />
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-sm leading-tight font-medium">
                                    {row.original.username ?? (
                                        <span className="text-muted-foreground italic">
                                            (no username)
                                        </span>
                                    )}
                                </p>
                                <p className="truncate text-xs leading-tight text-muted-foreground">
                                    {row.original.name ?? '—'}
                                </p>
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'wallet_code',
                header: () => (
                    <span className="text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground/80 uppercase">
                        Code
                    </span>
                ),
                cell: ({ row }) => (
                    <code className="rounded border bg-muted/40 px-1.5 py-0.5 font-mono text-[0.7rem] tracking-tight tabular-nums">
                        {row.original.wallet_code}
                    </code>
                ),
            },
            {
                accessorKey: 'telegram_id',
                header: () => (
                    <span className="text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground/80 uppercase">
                        Telegram
                    </span>
                ),
                cell: ({ row }) =>
                    row.original.telegram_id !== null ? (
                        <span className="inline-flex items-center gap-1 font-mono text-xs text-muted-foreground tabular-nums">
                            <Send className="size-3" />
                            {row.original.telegram_id}
                        </span>
                    ) : (
                        <span className="text-xs text-muted-foreground/50">
                            —
                        </span>
                    ),
            },
            {
                accessorKey: 'balance',
                header: () => (
                    <div className="text-right text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground/80 uppercase">
                        Balance
                    </div>
                ),
                cell: ({ row }) => {
                    const positive = isPositive(row.original.balance);
                    const held = isPositive(row.original.held_balance);

                    return (
                        <div className="text-right">
                            <p
                                className={cn(
                                    'font-mono text-sm font-semibold tracking-tight tabular-nums',
                                    positive
                                        ? 'text-foreground'
                                        : 'text-muted-foreground/60',
                                )}
                            >
                                {formatPeso(row.original.balance)}
                            </p>
                            {held && (
                                <p className="font-mono text-[0.65rem] text-muted-foreground tabular-nums">
                                    {formatPeso(row.original.held_balance)} held
                                </p>
                            )}
                        </div>
                    );
                },
            },
            {
                accessorKey: 'is_admin',
                header: () => (
                    <span className="text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground/80 uppercase">
                        Role
                    </span>
                ),
                cell: ({ row }) =>
                    row.original.is_admin ? (
                        <Badge variant="default" className="font-medium">
                            Admin
                        </Badge>
                    ) : (
                        <span className="text-xs text-muted-foreground/70">
                            Player
                        </span>
                    ),
            },
            {
                accessorKey: 'created_at',
                header: () => (
                    <span className="text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground/80 uppercase">
                        Joined
                    </span>
                ),
                cell: ({ row }) => (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span className="cursor-default text-xs text-muted-foreground tabular-nums">
                                {relativeTime(row.original.created_at)}
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {absoluteDateTime(row.original.created_at)}
                        </TooltipContent>
                    </Tooltip>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <Button
                            asChild
                            size="icon"
                            variant="ghost"
                            className="size-8 text-muted-foreground hover:text-foreground"
                            aria-label={`Open ${
                                row.original.username ??
                                row.original.wallet_code
                            }`}
                        >
                            <Link
                                href={adminUsersShow(row.original.id).url}
                                prefetch
                            >
                                <ArrowUpRight className="size-4" />
                            </Link>
                        </Button>
                    </div>
                ),
            },
        ],
        [],
    );

    const isModifierMac =
        typeof navigator !== 'undefined' &&
        /Mac|iPhone|iPad/.test(navigator.platform);

    return (
        <>
            <Head title="Admin · Users" />
            <div className="mx-auto max-w-7xl space-y-6 p-4 md:p-8">
                <header className="space-y-3">
                    <p className="text-[0.65rem] font-bold tracking-[0.2em] text-muted-foreground uppercase">
                        Admin · Operator console
                    </p>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div className="space-y-1">
                            <h1 className="text-3xl font-bold tracking-tight">
                                Users
                            </h1>
                            <p className="max-w-prose text-sm text-muted-foreground">
                                Search, audit, and adjust player wallets. Every
                                credit and debit writes a ledger row tagged with
                                the acting admin.
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-2 rounded-lg border bg-card px-3 py-2 text-xs tabular-nums">
                            <span className="font-mono text-muted-foreground">
                                page
                            </span>
                            <span className="font-mono text-sm font-semibold text-foreground">
                                {users.current_page}
                            </span>
                            <span className="text-muted-foreground/50">/</span>
                            <span className="font-mono text-sm text-muted-foreground">
                                {users.last_page}
                            </span>
                        </div>
                    </div>
                </header>

                {/* Stat strip — page-scoped quick reads */}
                <section className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard
                        icon={Users}
                        label="Total players"
                        value={users.total.toLocaleString('en-PH')}
                        caption="on file"
                    />
                    <StatCard
                        icon={UserPlus}
                        label="Funded"
                        value={`${stats.funded}/${users.data.length}`}
                        caption="on this page"
                    />
                    <StatCard
                        icon={Send}
                        label="Telegram"
                        value={`${stats.telegram}/${users.data.length}`}
                        caption="on this page"
                    />
                    <StatCard
                        icon={LockKeyhole}
                        label="Locked"
                        value={String(stats.locked)}
                        caption="on this page"
                        tone={stats.locked > 0 ? 'warning' : 'neutral'}
                    />
                </section>

                {status && (
                    <div className="flex items-center gap-2 rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm text-success">
                        <CheckCircle2 className="size-4" />
                        <span>{status}</span>
                    </div>
                )}

                {/* Command-bar search */}
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        ref={searchInputRef}
                        value={search}
                        onChange={(event) =>
                            setSearch(event.currentTarget.value)
                        }
                        placeholder="Search username, name, wallet code, or telegram id…"
                        className="h-11 pr-20 pl-10 text-sm shadow-xs"
                        aria-label="Search users"
                    />
                    <kbd className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 rounded border bg-muted px-1.5 py-0.5 font-mono text-[0.65rem] text-muted-foreground">
                        {isModifierMac ? '⌘' : 'Ctrl'} K
                    </kbd>
                </div>

                <DataTable<UserRow>
                    columns={columns}
                    data={users.data}
                    meta={users}
                    onlyOnPaginate={['users']}
                    emptyMessage={
                        filters.search
                            ? `No matches for "${filters.search}".`
                            : 'No users on file yet.'
                    }
                />
            </div>
        </>
    );
}

function StatCard({
    icon: Icon,
    label,
    value,
    caption,
    tone = 'neutral',
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    value: string;
    caption: string;
    tone?: 'neutral' | 'warning';
}) {
    return (
        <Card className="overflow-hidden">
            <CardContent className="flex items-center gap-3 p-4">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-lg',
                        tone === 'warning'
                            ? 'bg-warning/15 text-warning'
                            : 'bg-muted/60 text-muted-foreground',
                    )}
                >
                    <Icon className="size-4" />
                </span>
                <div className="min-w-0">
                    <p className="text-[0.6rem] font-bold tracking-[0.14em] text-muted-foreground/80 uppercase">
                        {label}
                    </p>
                    <p className="font-mono text-lg leading-tight font-bold tabular-nums">
                        {value}
                    </p>
                    <p className="text-[0.65rem] text-muted-foreground">
                        {caption}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
