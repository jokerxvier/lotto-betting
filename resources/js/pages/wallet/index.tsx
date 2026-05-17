import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatPeso } from '@/lib/money';

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

const TYPE_LABEL: Record<string, string> = {
    admin_topup: 'Top-up',
    deposit: 'Deposit',
    withdrawal: 'Withdrawal',
    bet_debit: 'Bet placed',
    bet_payout: 'Winnings',
    refund: 'Refund',
};

export default function WalletIndex({ wallet, transactions }: Props) {
    return (
        <>
            <Head title="Wallet" />
            <div className="space-y-6 p-4 md:p-6">
                <Heading
                    title="Wallet"
                    description="Your balance and recent activity."
                />

                <Card>
                    <CardHeader>
                        <CardDescription>Balance</CardDescription>
                        <CardTitle className="text-3xl tabular-nums">
                            {formatPeso(wallet.balance)}
                        </CardTitle>
                    </CardHeader>
                    {wallet.wallet_code && (
                        <CardContent className="text-sm text-muted-foreground">
                            Use this code when depositing:{' '}
                            <span className="font-mono font-semibold text-foreground">
                                {wallet.wallet_code}
                            </span>
                        </CardContent>
                    )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent activity</CardTitle>
                        <CardDescription>
                            Last {transactions.length} transactions
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {transactions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No transactions yet.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border text-sm">
                                {transactions.map((tx) => {
                                    const isCredit = !tx.amount.startsWith('-');

                                    return (
                                        <li
                                            key={tx.id}
                                            className="flex items-center justify-between py-3"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {TYPE_LABEL[tx.type] ??
                                                        tx.type}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {new Date(
                                                        tx.created_at,
                                                    ).toLocaleString('en-PH')}
                                                </p>
                                            </div>
                                            <div className="text-right tabular-nums">
                                                <p
                                                    className={
                                                        isCredit
                                                            ? 'font-semibold text-success'
                                                            : 'font-semibold text-destructive'
                                                    }
                                                >
                                                    {isCredit ? '+' : ''}
                                                    {formatPeso(tx.amount)}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Bal{' '}
                                                    {formatPeso(
                                                        tx.balance_after,
                                                    )}
                                                </p>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
