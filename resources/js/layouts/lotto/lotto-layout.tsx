import { Link, usePage } from '@inertiajs/react';
import { Wallet as WalletIcon } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import LottoTabBar from '@/components/lotto/lotto-tab-bar';
import PayTicketsBar from '@/components/lotto/pay-tickets-bar';
import ThemeToggle from '@/components/theme-toggle';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { CartProvider } from '@/contexts/cart-context';
import { useInitials } from '@/hooks/use-initials';
import { formatPeso } from '@/lib/money';
import type { User } from '@/types/auth';

type SharedProps = {
    auth: {
        user: User | null;
        wallet: { balance: string; wallet_code: string } | null;
    };
};

/**
 * Mobile-first product shell. Constrained to a 300–480px column (centered
 * on desktop) so the lotto product always feels like a mobile app rather
 * than stretching across the viewport. The muted outer band is the visible
 * "device frame" on wide screens; safe-area insets cover the iOS Telegram
 * webview so the bottom nav doesn't disappear behind the home indicator.
 */
export default function LottoLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<SharedProps>().props;
    const balanceIsZero =
        !auth.wallet ||
        Number.parseFloat(auth.wallet.balance || '0') === 0;
    const getInitials = useInitials();

    return (
        <CartProvider>
            <div className="flex min-h-svh justify-center bg-muted/60">
                <div className="relative flex w-full max-w-[480px] min-w-[300px] flex-col bg-background shadow-sm ring-1 ring-border/40 md:my-4 md:rounded-2xl md:shadow-xl">
                    <header className="sticky top-0 z-20 flex items-center gap-2 border-b border-border bg-background/85 px-4 py-3 backdrop-blur-md">
                        <Link
                            href="/lotto"
                            className="group flex items-center gap-2"
                        >
                            <span className="relative flex size-9 items-center justify-center overflow-hidden rounded-lg bg-primary text-sm font-black text-primary-foreground shadow-sm transition-transform group-active:scale-95">
                                <span className="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,oklch(1_0_0/0.3),transparent_55%)]" />
                                <span className="relative">L</span>
                            </span>
                            <span className="text-sm leading-none font-bold tracking-tight">
                                Lotto
                                <span className="text-primary">PH</span>
                            </span>
                        </Link>

                        <div className="ml-auto flex items-center gap-1.5">
                            <ThemeToggle />

                            {auth.wallet && (
                                <Link
                                    href="/wallet"
                                    aria-label={`Wallet: ${formatPeso(auth.wallet.balance)}`}
                                    className={
                                        balanceIsZero
                                            ? 'flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-sm font-bold text-muted-foreground tabular-nums transition-colors hover:bg-muted active:scale-95'
                                            : 'flex items-center gap-1.5 rounded-full bg-success px-3 py-1.5 text-sm font-bold text-success-foreground tabular-nums shadow-[0_1px_2px_rgba(0,0,0,0.18),0_6px_14px_-4px_oklch(0.65_0.18_145/0.55)] transition-transform active:scale-95'
                                    }
                                >
                                    <WalletIcon className="size-3.5" />
                                    {formatPeso(auth.wallet.balance)}
                                </Link>
                            )}

                            {auth.user && (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <button
                                            type="button"
                                            aria-label="Account menu"
                                            className="flex size-9 items-center justify-center rounded-full bg-muted text-sm font-bold text-foreground transition-transform hover:bg-accent active:scale-95"
                                        >
                                            {getInitials(
                                                auth.user.name ??
                                                    auth.user.username ??
                                                    auth.user.wallet_code,
                                            )}
                                        </button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        className="min-w-56 rounded-lg"
                                    >
                                        <UserMenuContent user={auth.user} />
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
                        </div>
                    </header>

                    <main className="flex-1 pb-36">{children}</main>

                    <PayTicketsBar />
                    <LottoTabBar />
                </div>
            </div>
        </CartProvider>
    );
}
