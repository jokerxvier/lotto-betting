import { Head } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Coins,
    Receipt,
    RotateCcw,
    Wallet as WalletIcon,
} from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

type Transaction = {
    id: number;
    type: string;
    amount: string;
    balance_after: string;
    created_at: string;
};

type Props = {
    wallet: {
        balance: string;
        held_balance: string;
        wallet_code: string | null;
    };
    transactions: Transaction[];
};

type TypeMeta = {
    label: string;
    icon: ComponentType<SVGProps<SVGSVGElement>>;
};

const TYPE_META: Record<string, TypeMeta> = {
    admin_topup: { label: 'Top-up', icon: ArrowDownToLine },
    deposit: { label: 'Deposit', icon: ArrowDownToLine },
    withdrawal: { label: 'Withdrawal', icon: ArrowUpFromLine },
    bet_debit: { label: 'Bet placed', icon: Receipt },
    bet_payout: { label: 'Winnings', icon: Coins },
    refund: { label: 'Refund', icon: RotateCcw },
};

const formatRelative = (iso: string): string => {
    const date = new Date(iso);
    const now = new Date();
    const sameDay =
        date.getFullYear() === now.getFullYear() &&
        date.getMonth() === now.getMonth() &&
        date.getDate() === now.getDate();

    return sameDay
        ? date.toLocaleTimeString('en-PH', {
              hour: 'numeric',
              minute: '2-digit',
          })
        : date.toLocaleDateString('en-PH', {
              month: 'short',
              day: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
          });
};

export default function WalletIndex({ wallet, transactions }: Props) {
    const balanceNum = Number.parseFloat(wallet.balance || '0');

    return (
        <>
            <Head title="Wallet" />
            <div className="space-y-5 p-4">
                <header className="space-y-0.5">
                    <h1 className="text-xl font-bold tracking-tight">
                        Wallet
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Your balance and recent activity.
                    </p>
                </header>

                <Card className="overflow-hidden">
                    <CardContent className="flex flex-col items-center gap-2 p-6 text-center">
                        <div className="relative flex size-16 items-center justify-center overflow-hidden rounded-full bg-primary text-primary-foreground shadow-[0_4px_18px_-2px_oklch(0.58_0.2_255/0.5)]">
                            <span className="absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,oklch(1_0_0/0.4),transparent_55%)]" />
                            <WalletIcon className="relative size-8" />
                        </div>
                        {wallet.wallet_code && (
                            <p className="font-mono text-[0.65rem] font-bold tracking-[0.2em] text-muted-foreground uppercase">
                                {wallet.wallet_code}
                            </p>
                        )}
                        <p
                            className={cn(
                                'text-4xl font-black tabular-nums',
                                balanceNum > 0
                                    ? 'text-success'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {formatPeso(wallet.balance)}
                        </p>
                        <p className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                            Wallet balance
                        </p>
                    </CardContent>
                </Card>

                <section className="space-y-2">
                    <header className="flex items-baseline justify-between">
                        <h2 className="text-sm font-bold tracking-wide">
                            Recent activity
                        </h2>
                        {transactions.length > 0 && (
                            <span className="text-[0.65rem] font-bold tracking-wider text-muted-foreground uppercase">
                                Last {transactions.length}
                            </span>
                        )}
                    </header>

                    {transactions.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border bg-card p-8 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted text-muted-foreground">
                                <Coins className="size-5" />
                            </div>
                            <p className="text-sm font-semibold">
                                No transactions yet
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Top up or place a bet to see activity.
                            </p>
                        </div>
                    ) : (
                        <ul className="space-y-2">
                            {transactions.map((tx) => {
                                const isCredit = !tx.amount.startsWith('-');
                                const meta = TYPE_META[tx.type] ?? {
                                    label: tx.type,
                                    icon: Coins,
                                };
                                const Icon = meta.icon;

                                return (
                                    <li
                                        key={tx.id}
                                        className={cn(
                                            'flex items-center gap-3 rounded-xl border border-border border-l-4 bg-card p-3',
                                            isCredit
                                                ? 'border-l-success'
                                                : 'border-l-destructive/70',
                                        )}
                                    >
                                        <div
                                            className={cn(
                                                'flex size-9 shrink-0 items-center justify-center rounded-lg',
                                                isCredit
                                                    ? 'bg-success/15 text-success'
                                                    : 'bg-destructive/10 text-destructive',
                                            )}
                                        >
                                            <Icon className="size-4" />
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold leading-tight">
                                                {meta.label}
                                            </p>
                                            <p className="text-[0.7rem] text-muted-foreground">
                                                {formatRelative(tx.created_at)}
                                            </p>
                                        </div>
                                        <div className="text-right tabular-nums">
                                            <p
                                                className={cn(
                                                    'text-sm font-bold',
                                                    isCredit
                                                        ? 'text-success'
                                                        : 'text-destructive',
                                                )}
                                            >
                                                {isCredit ? '+' : ''}
                                                {formatPeso(tx.amount)}
                                            </p>
                                            <p className="text-[0.65rem] text-muted-foreground">
                                                Bal{' '}
                                                {formatPeso(tx.balance_after)}
                                            </p>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
