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
            className="sticky bottom-0 z-10 grid grid-cols-4 border-t border-border bg-background/95 backdrop-blur"
        >
            {TABS.map((tab) => {
                const active = !tab.disabled && isActive(tab.matches, current);
                const Icon = tab.icon;

                const content = (
                    <span
                        className={cn(
                            'flex flex-col items-center gap-1 py-2 text-xs',
                            tab.disabled
                                ? 'text-muted-foreground/60'
                                : active
                                  ? 'font-semibold text-primary'
                                  : 'text-muted-foreground',
                        )}
                    >
                        <Icon className="size-5" />
                        <span className="flex items-center gap-1">
                            {tab.label}
                            {tab.disabled && (
                                <Badge
                                    variant="outline"
                                    className="h-4 rounded-sm px-1 text-[0.6rem]"
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
                            className="cursor-not-allowed"
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
                    >
                        {content}
                    </Link>
                );
            })}
        </nav>
    );
}
