import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import LottoLayout from '@/layouts/lotto/lotto-layout';
import SettingsLayout from '@/layouts/settings/layout';
import '@/types/telegram';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name === 'lotto/home':
            case name === 'wallet/index':
            case name === 'tickets/index':
            case name === 'tickets/show':
            case name === 'results/index':
                return LottoLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

bootTelegramWebApp();

/**
 * When the page is loaded inside the Telegram in-app browser, Telegram
 * injects a signed `initData` querystring at `window.Telegram.WebApp.initData`.
 * We POST it once on first paint to /auth/telegram/web-app — the server
 * validates the HMAC, signs the user in, and redirects to /lotto (or
 * /auth/setup-pin if the account is brand-new). The post is gated to /
 * and /login because those are the only pages a guest can land on; once
 * the user is authenticated the redirect lands them elsewhere and the
 * next page load skips this branch.
 */
function bootTelegramWebApp(): void {
    // SSR (Inertia warm-up runs in Node) has no `window`. Bail before any
    // browser-only access.
    if (typeof window === 'undefined') {
        return;
    }

    const webApp = window.Telegram?.WebApp;

    if (!webApp?.initData) {
        return;
    }

    webApp.ready();
    webApp.expand?.();

    const path = window.location.pathname;

    if (path !== '/' && path !== '/login') {
        return;
    }

    const csrf = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content;

    if (!csrf) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/auth/telegram/web-app';

    for (const [name, value] of [
        ['_token', csrf],
        ['init_data', webApp.initData],
    ]) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        form.appendChild(input);
    }

    document.body.appendChild(form);
    form.submit();
}
