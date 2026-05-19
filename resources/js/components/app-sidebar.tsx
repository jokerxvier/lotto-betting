import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Settings, Trophy, Wallet } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { lotto } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as adminDrawsIndex } from '@/routes/admin/draws';
import { edit as adminSettingsEdit } from '@/routes/admin/settings';
import { create as adminWalletsCreate } from '@/routes/admin/wallets';
import type { NavItem } from '@/types';

const adminNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: adminDashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Draws',
        href: adminDrawsIndex(),
        icon: Trophy,
    },
    {
        title: 'Wallets',
        href: adminWalletsCreate(),
        icon: Wallet,
    },
    {
        title: 'Settings',
        href: adminSettingsEdit(),
        icon: Settings,
    },
];

const playerNavItems: NavItem[] = [
    {
        title: 'Lotto',
        href: lotto(),
        icon: LayoutGrid,
    },
];

export function AppSidebar() {
    const { props } = usePage<{
        auth?: { user?: { is_admin?: boolean } | null };
    }>();
    const isAdmin = props.auth?.user?.is_admin === true;
    const items = isAdmin ? adminNavItems : playerNavItems;
    const homeHref = isAdmin ? adminDashboard() : lotto();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={homeHref} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={items} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
