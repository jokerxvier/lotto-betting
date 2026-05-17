import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import LottoTabBar from '@/components/lotto/lotto-tab-bar';
import PayTicketsBar from '@/components/lotto/pay-tickets-bar';
import ThemeToggle from '@/components/theme-toggle';
import { CartProvider } from '@/contexts/cart-context';
import { formatPeso } from '@/lib/money';

type SharedProps = {
    auth: {
        user: { username: string | null; name: string | null } | null;
        wallet: { balance: string; wallet_code: string } | null;
    };
};

/**
 * Mobile-first product shell. Constrained to a 300–480px column (centered
 * on desktop) so the lotto product always feels like a mobile app rather
 * than stretching across the viewport. The muted outer band is the visible
 * "device frame" on wide screens.
 */
export default function LottoLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<SharedProps>().props;

    return (
        <CartProvider>
            <div className="flex min-h-svh justify-center bg-muted/40">
                <div className="flex w-full max-w-[480px] min-w-[300px] flex-col bg-background shadow-sm">
                    <header className="sticky top-0 z-10 flex items-center gap-2 border-b border-border bg-background/95 px-4 py-3 backdrop-blur">
                        <Link
                            href="/lotto"
                            className="flex items-center gap-2"
                        >
                            <div className="flex size-9 items-center justify-center rounded-md bg-primary text-sm font-bold text-primary-foreground">
                                L
                            </div>
                            <span className="text-sm font-semibold">
                                Lotto PH
                            </span>
                        </Link>

                        <div className="ml-auto flex items-center gap-1">
                            <ThemeToggle />

                            {auth.wallet && (
                                <Link
                                    href="/wallet"
                                    className="rounded-full bg-primary px-4 py-1.5 text-sm font-semibold text-primary-foreground tabular-nums shadow-sm"
                                >
                                    {formatPeso(auth.wallet.balance)}
                                </Link>
                            )}
                        </div>
                    </header>

                    <main className="flex-1 pb-32">{children}</main>

                    <PayTicketsBar />
                    <LottoTabBar />
                </div>
            </div>
        </CartProvider>
    );
}
