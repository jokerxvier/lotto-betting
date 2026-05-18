import { Link, usePage } from '@inertiajs/react';
import { Home, Receipt, Trophy, Wallet as WalletIcon } from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Tab = {
    label: string;
    icon: ComponentType<SVGProps<SVGSVGElement>>;
    href?: string;
    matches?: string[];
    disabled?: boolean;
};

const TABS: Tab[] = [
    {
        label: 'Lotto',
        icon: Home,
        href: '/lotto',
        matches: ['/lotto', '/games'],
    },
    {
        label: 'Results',
        icon: Trophy,
        href: '/results',
        matches: ['/results'],
    },
    {
        label: 'Tickets',
        icon: Receipt,
        href: '/tickets',
        matches: ['/tickets'],
    },
    {
        label: 'Wallet',
        icon: WalletIcon,
        href: '/wallet',
        matches: ['/wallet'],
    },
];

function isActive(matches: string[] | undefined, current: string): boolean {
    if (!matches) {
        return false;
    }

    return matches.some(
        (prefix) => current === prefix || current.startsWith(`${prefix}/`),
    );
}

export default function LottoTabBar() {
    const { url } = usePage();
    const current = url.split('?')[0];

    return (
        <nav
            aria-label="Primary"
            className="sticky bottom-0 z-10 grid grid-cols-4 border-t border-white/10 bg-surface-nav text-surface-nav-foreground shadow-[0_-2px_12px_oklch(0_0_0/0.25)]"
            style={{
                paddingBottom:
                    'max(env(safe-area-inset-bottom), 0.5rem)',
            }}
        >
            {TABS.map((tab) => {
                const active = !tab.disabled && isActive(tab.matches, current);
                const Icon = tab.icon;

                const content = (
                    <span
                        className={cn(
                            'relative flex h-full min-h-[4.5rem] flex-col items-center justify-center gap-1 px-1 pt-2 pb-1 text-[0.7rem] leading-none tracking-wide uppercase transition-colors',
                            tab.disabled
                                ? 'font-semibold text-surface-nav-foreground/50'
                                : active
                                  ? 'font-bold text-primary'
                                  : 'font-semibold text-surface-nav-foreground/75 hover:text-surface-nav-foreground',
                        )}
                    >
                        {active && (
                            <span
                                aria-hidden
                                className="absolute top-0 left-1/2 h-[3px] w-12 -translate-x-1/2 rounded-full bg-primary shadow-[0_2px_8px_oklch(0.58_0.2_255/0.55)]"
                            />
                        )}
                        <Icon
                            className="size-[1.4rem] shrink-0"
                            strokeWidth={active ? 2.4 : 1.8}
                        />
                        <span className="flex items-center gap-1 pb-0.5">
                            {tab.label}
                            {tab.disabled && (
                                <Badge
                                    variant="outline"
                                    className="h-3.5 rounded-sm border-surface-nav-foreground/30 px-1 text-[0.55rem] text-surface-nav-foreground/40"
                                >
                                    Soon
                                </Badge>
                            )}
                        </span>
                    </span>
                );

                if (tab.disabled || !tab.href) {
                    return (
                        <div
                            key={tab.label}
                            aria-disabled
                            className="flex cursor-not-allowed select-none"
                        >
                            {content}
                        </div>
                    );
                }

                return (
                    <Link
                        key={tab.label}
                        href={tab.href}
                        prefetch
                        aria-current={active ? 'page' : undefined}
                        className="flex transition-transform active:scale-95"
                    >
                        {content}
                    </Link>
                );
            })}
        </nav>
    );
}
